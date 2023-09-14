# Purpose

This is a Wordpress plugin for Elementor pages and blocks validation.

## Installation

Check [install](./doc/INSTALL.md) document.

## Bundle

```bash
npm run bundle
```

## Next features

- [ ] the plugin should be accessible from admin menu
- [ ] have the ability to validate each element in a somewhat genealogical way, headers and footers include. We don't go lower than elementor blocks
- [ ] I would like to display this in a simple interface adapted to the Wordpress administration interface
- [ ] I would like us to be able to validate each element individually, in a simple way
- [ ] I would like that when the validation is modifiable only by the administrator but not the client
- [ ] I would like that when we click on an element, all the elements which are descendants are also validated
- [ ] I would like if we validate something, we can no longer modify the element and its sub-elements in question, if possible
- [ ] I would like to have rights management so that the client can only access this plugin in the administration interface
- [ ] I would like the validation to be modifiable only by the administrator but not the client
- [ ] I would like the administrator alone to be able to cancel the validation

## Infos

- **package.json** : Use it for JavaScript dependencies and scripts related to your plugin development.
- **composer.json** : Use it for WordPress-specific PHP dependencies and to set the minimum PHP version required, among other things.
- **Actions GitHub** : Use them to automate packaging and release creation tasks on GitHub. These actions can be customized to your specific needs.
