# GDMexico_CheckoutRegister

Adds an inline **Create account** form to the Magento 2 checkout authentication modal. The customer signs in after account creation (unless email confirmation is required).

## Requirements

- PHP `>=7.4 <8.6` (source-code compatibility; actual supported PHP versions depend on the installed Magento release and its dependencies).
- Magento 2 checkout, customer and store modules.
- Composer 2 and an existing Magento installation.

## Composer installation (Git VCS)

Publish this package in a Git repository with its `composer.json` at the repository root and tag a release, e.g. `1.0.1`.

In the Magento project (only if the package is not on Packagist/private Composer repository):

```bash
composer config repositories.gdmexico-checkout-register vcs git@github.com:YOUR_ORG/module-checkout-register.git
composer require gdmexico/module-checkout-register:^1.0
php bin/magento module:enable GDMexico_CheckoutRegister
php bin/magento setup:upgrade
php bin/magento cache:flush
```

On production-mode deployments, additionally run `php bin/magento setup:di:compile` and `php bin/magento setup:static-content:deploy -f` as appropriate for the installed Magento release.

## Notes

- Composer's `php` constraint does **not** guarantee that every Magento version supports PHP 8.5. Match Magento's official compatibility matrix before deploying.
- The module overrides the checkout authentication Knockout template via a mixin. Check other modules overriding the same component/template for conflicts.
- Test checkout registration, email confirmation, existing-customer login, customer group assignment, and cart preservation in your actual Magento environment.
- If using a public Composer repository, submit the repository to Packagist or use your private package registry; Git tags alone don't automatically make a package discoverable.
