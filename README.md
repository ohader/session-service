# TYPO3 Session Service Helper

This extension is just a helper tool and currently is **experimental**.

## Domain modelling

Extbase domain entities that shall be related to an existing `FrontendUser` entity
require a dedicates property of type `\TYPO3\CMS\Extbase\Domain\Model\FrontendUser`
(or any sub-class of this model).

In the following example `Customer` is the entity to be resolved base on a website
frontend user.

```php
class Customer extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * Frontend User
     *
     * @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser
     */
    protected $user;
```

## Resolving entity subject

In order to resolve subjects of type `Customer` based on the current logged in
frontend user the session service helper provides the following API:

```php
$currentCustomer = SubjectResolver::get()
    ->forClassName(Customer::class)
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

## Using session subject collection

`SubjectCollection` inherits from `\ArrayObject` and thus can be used like
an array in PHP. In case there are items in the session for the mandatory
`scope` (`project/shopping-cart` in the example below), those items are
resolved from session storage automatically.

```php
/** @var $entity \TYPO3\CMS\Extbase\DomainObject\AbstractEntity */
$entity = $this->entityRepository->findByIdentifier(123);
$collection = SubjectCollection::get('project/shopping-cart');
$collection[] = $entity;
$collection->persist();
```

In case no frontend user is logged in or could not be mapped to a subject an
exception of type `InvalidSessionException` is thrown.
