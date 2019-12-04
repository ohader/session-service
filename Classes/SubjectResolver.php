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
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Usage in order to resolve subject based on current frontend user:
 *
 *   $customer = SubjectResolver::get()
 *     ->forClassName(Domain\Model\Customer::class)
 *     ->forPropertyName('frontendUser')
 *     ->resolve();
 */
class SubjectResolver
{
    /**
     * @var string
     */
    private $className;

    /**
     * @var string
     */
    private $propertyName;

    public static function get(): self
    {
        return GeneralUtility::makeInstance(static::class);
    }

    public function forClassName(string $className): self
    {
        $this->className = $className;
        return $this;
    }

    public function forPropertyName(string $propertyName): self
    {
        $this->propertyName = $propertyName;
        return $this;
    }

    public function resolve(): AbstractEntity
    {
        $this->assertClassName();
        $this->assertPropertyName();

        $frontendUserId = $this->assertFrontendUserId();
        $subject = $this->resolveSubject($frontendUserId);
        if (!empty($subject)) {
            return $subject;
        }

        throw new InvalidSessionException(
            sprintf(
                'Could not resolve subject of type "%s" for current frontend user',
                $this->className
            ),
            1543941067
        );
    }

    private function assertClassName()
    {
        if (!is_a($this->className, AbstractEntity::class, true)) {
            throw new \LogicException(
                sprintf(
                    'Class "%s" must inherit from class "%s"',
                    $this->className,
                    AbstractEntity::class
                ),
                1543941064
            );
        }
    }

    private function assertPropertyName()
    {
        $classSchema = $this->getReflectionService()->getClassSchema($this->className);
        $propertyDefinition = $classSchema->getProperty($this->propertyName);

        if (empty($propertyDefinition)) {
            throw new \LogicException(
                sprintf(
                    'Property "%s" could not be found',
                    $this->propertyName
                ),
                1543941065
            );
        }
        if (!is_a($propertyDefinition['type'] ?? null, FrontendUser::class, true)) {
            throw new \LogicException(
                sprintf(
                    'Type of property "%s" must be "%s", got "%s"',
                    $this->propertyName,
                    FrontendUser::class,
                    $propertyDefinition['type'] ?? null
                ),
                1543941066
            );
        }
    }

    private function assertFrontendUserId(): int
    {
        $frontendUserId = $this->getFrontendUserId();
        if (!empty($frontendUserId)) {
            return $frontendUserId;
        }
        throw new InvalidSessionException(
            'No frontend user logged in',
            1543941063
        );
    }

    /**
     * @param int $frontendUserId
     * @return AbstractEntity|null
     */
    private function resolveSubject(int $frontendUserId): ?AbstractEntity
    {
        $query = $this->getPersistenceManager()
            ->createQueryForType($this->className);
        $query->getQuerySettings()
            ->setRespectStoragePage(false);
        $query->matching(
            $query->equals($this->propertyName, $frontendUserId)
        );
        $result = $query->execute();
        $amount = $result->count();
        if ($amount > 1) {
            throw new SubjectException(
                sprintf(
                    'FrontendUser assignment to Subject is ambiguous, having %d candidates, expected just one.',
                    $amount
                ),
                1575391208
            );
        }
        if ($amount === 1) {
            return $result->getFirst();
        }
        return null;
    }

    private function getReflectionService(): ReflectionService
    {
        return $this->getObjectManager()->get(ReflectionService::class);
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
     * @return int|null
     */
    private function getFrontendUserId(): ?int
    {
        return $this->getFrontendController()->fe_user->user['uid'] ?? null;
    }

    /**
     * @return TypoScriptFrontendController
     */
    private function getFrontendController()
    {
        return $GLOBALS['TSFE'];
    }
}