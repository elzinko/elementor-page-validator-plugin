# Purpose

This is a Wordpress plugin for Elementor pages and blocks validation.

## Installation

Check [install](./doc/INSTALL.md) document.

## Bundle

```bash
npm run bundle
```

## Next features

- [x] the plugin should be accessible from admin menu
- [ ] show all Elementor pages and blocs in admin page
- [ ] be able to validate each element individually
- [ ] when an element is validated, all its children are also validated
- [ ] add a progress bar for validation
- [ ] the client can only see this plugin in the administration interface
- [ ] administrator can undo validation
- [ ] client cannot undo validation
- [ ] when an element is validated, all its related Elementor blocs should be locked for update
- [ ] history of validations (who, what, when, why) should be printed in a sub menu of teh plugin
- [ ] show related elements screenshot when validated

## Infos

- **package.json** : Use it for JavaScript dependencies and scripts related to your plugin development.
- **composer.json** : Use it for WordPress-specific PHP dependencies and to set the minimum PHP version required, among other things.
- **Actions GitHub** : Use them to automate packaging and release creation tasks on GitHub. These actions can be customized to your specific needs.
