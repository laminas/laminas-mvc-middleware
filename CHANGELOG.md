# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.0.0 - TBD

### Added

- [#1](https://github.com/zendframework/zend-mvc-middleware) extracts from zend-mvc
  optional middleware dispatch support as a separate opt-in package and
  provides same middleware functionality as is present in zend-mvc 2.1
  releases.

  To migrate mvc application that already uses zend-mvc optional middleware support,
  install zend-mvc-middleware, pinning it to 1.0.* releases:
  - `composer remove zendframework/zend-psr7bridge`
  - `composer require zendframework/zend-mvc-middleware:~1.0.0`
  - Enable `Zend\Mvc\Middleware` module in the application

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
