# TYPO3 Session Service Helper

This extension is just a helper tool and currently is **experimental**.

## Domain modelling

Extbase domain entities that shall be related to an existing `FrontendUser` entity
require a dedicates property of type `\TYPO3\CMS\Extbase\Domain\Model\FrontendUser`
(or any sub-class of this model).

In the following example `Volunteer` is the entity to be resolved base on a website
frontend user.

```php
class Volunteer extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * Frontend User
     *
     * @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser
     */
    protected $user;
```

## Resolving entity subject

In order to resolve subjects of type `Volunteer` based on the current logged in
frontend user the session service helper provides the following API:

```php
$this->currentVolunteer = SubjectResolver::get()
    ->forClassName(Volunteer::class)
    ->forPropertyName('user')
    ->resolve();
```

In case no frontend user is logged in or could not be mapped to a subject an
exception of type `InvalidSessionException` is thrown. In case more than one
subjects would be resolved, a `SubjectException` is thrown.

## Resolving frontend user entity

In order to resolve the Extbase entity (or a sub-class) that is related to the
currently logged in frontend user, the following API is provided:

```php
$frontendUser = FrontendUserResolver::get()
    ->forClassName(\TYPO3\CMS\Extbase\Domain\Model\FrontendUser::class)
    ->resolve();
```

In case no frontend user is logged in or could not be mapped to a subject an
exception of type `InvalidSessionException` is thrown.
