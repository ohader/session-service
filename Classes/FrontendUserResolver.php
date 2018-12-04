<?php
declare(strict_types = 1);
namespace OliverHader\SessionService;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Usage in order to resolve frontend user entity:
 *
 *   $customer = FrontendUserResolver::get()
 *     ->forClassName(Domain\Model\FrontendUser::class)
 *     ->resolve();
 */
class FrontendUserResolver
{
    /**
     * @var string
     */
    private $className = FrontendUser::class;

    public static function get(): self
    {
        return GeneralUtility::makeInstance(static::class);
    }

    public function forClassName(string $className): self
    {
        $this->className = $className;
        return $this;
    }

    public function resolve(): FrontendUser
    {
        $this->assertClassName();

        $frontendUserId = $this->assertFrontendUserId();
        $subject = $this->resolveSubject($frontendUserId);
        if (!empty($subject)) {
            return $subject;
        }

        throw new InvalidSessionException(
            sprintf(
                'Could not resolve frontend user of type "%s"',
                $this->className
            ),
            1543941072
        );
    }

    private function assertClassName()
    {
        if (!is_a($this->className, FrontendUser::class, true)) {
            throw new \LogicException(
                sprintf(
                    'Class "%s" must inherit from class "%s"',
                    $this->className,
                    FrontendUser::class
                ),
                1543941071
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
            1543941073
        );
    }

    /**
     * @param int $frontendUserId
     * @return FrontendUser|null
     */
    private function resolveSubject(int $frontendUserId): ?FrontendUser
    {
        $query = $this->getPersistenceManager()
            ->createQueryForType($this->className)
            ->setLimit(1);
        $query->getQuerySettings()
            ->setRespectStoragePage(false);
        $query->matching(
            $query->equals('uid', $frontendUserId)
        );
        $result = $query->execute();
        if ($result->count() === 1) {
            return $result->getFirst();
        }
        return null;
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