<?php
declare(strict_types = 1);
namespace OliverHader\SessionService;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Usage in order to resolve subject based on current frontend user:
 *
 *   $collection = SubjectCollection::get('project/shopping-cart');
 *   $collection[] = $entity; // $entity must implement AbstractEntity
 *   $collection->persist();
 */
class SubjectCollection extends \ArrayObject implements \JsonSerializable
{
    /**
     * @var string
     */
    private $scope;

    public static function get(string $scope, array $input = []): self
    {
        return GeneralUtility::makeInstance(static::class, $scope, $input);
    }

    public function __construct(string $scope, array $input = [])
    {
        if ($scope === '') {
            throw new \LogicException('Scope cannot be empty', 1575394667);
        }
        parent::__construct($input);
        $this->scope = $scope;
        if (empty($input)) {
            $this->retrieve();
        }
    }

    public function jsonSerialize(): array
    {
        return array_map(
            function (AbstractEntity $entity) {
                $this->assertUid($entity->getUid());
                return [
                    'class' => get_class($entity),
                    'uid' => $entity->getUid(),
                ];
            },
            $this->getArrayCopy()
        );
    }

    public function persist(): self
    {
        $this->getFrontendUser()->setAndSaveSessionData(
            $this->scope,
            json_encode($this)
        );
        return $this;
    }

    public function retrieve(): self
    {
        $sessionData = $this->getFrontendUser()->getSessionData($this->scope);
        if (!is_string($sessionData)) {
            return $this;
        }
        $collection = json_decode($sessionData, true);
        if (!is_array($collection)) {
            return $this;
        }
        $collection = array_map(
            function (array $item) {
                $this->assertClassName($item['class']);
                $this->assertUid($item['uid']);
                return $this->resolveSubject(
                    $item['class'],
                    $item['uid']
                );
            },
            $collection
        );
        $this->exchangeArray($collection);
        return $this;
    }

    public function purge(): self
    {
        $this->exchangeArray([]);
        $this->persist();
        return $this;
    }

    private function assertClassName(string $className)
    {
        if (!is_a($className, AbstractEntity::class, true)) {
            throw new \LogicException(
                sprintf(
                    'Class "%s" must inherit from class "%s"',
                    $className,
                    AbstractEntity::class
                ),
                1575394669
            );
        }
    }

    private function assertUid(?int $uid)
    {
        if ($uid === null || $uid <= 0) {
            throw new \LogicException(
                'Uid must be positive integer. Consider calling PersistenceManage::persistAll first...',
                1575394670
            );
        }
    }

    /**
     * @param string $className
     * @param int $uid
     * @return AbstractEntity|null
     */
    private function resolveSubject(string $className, int $uid): ?AbstractEntity
    {
        $query = $this->getPersistenceManager()
            ->createQueryForType($className)
            ->setLimit(1);
        $query->getQuerySettings()
            ->setRespectStoragePage(false);
        $query->matching(
            $query->equals('uid', $uid)
        );
        return $query->execute()->getFirst();
    }

    private function getPersistenceManager(): PersistenceManagerInterface
    {
        return $this->getObjectManager()->get(PersistenceManagerInterface::class);
    }

    private function getObjectManager(): ObjectManager
    {
        return GeneralUtility::makeInstance(ObjectManager::class);
    }

    /**
     * @return FrontendUserAuthentication|null
     */
    private function getFrontendUser(): ?FrontendUserAuthentication
    {
        return $this->getFrontendController()->fe_user ?? null;
    }

    /**
     * @return TypoScriptFrontendController
     */
    private function getFrontendController()
    {
        return $GLOBALS['TSFE'];
    }
}