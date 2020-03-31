# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.1.0 - TBD

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.0 - 2020-03-31

### Added

- [zendframework/zend-mvc-middleware#1](https://github.com/laminas/laminas-mvc-middleware) extracts from laminas-mvc
  optional middleware dispatch support as a separate opt-in package and
  provides same middleware functionality as is present in laminas-mvc 2.1
  releases.

  To migrate mvc application that already uses laminas-mvc optional middleware support,
  install laminas-mvc-middleware, pinning it to 1.0.* releases:
  - `composer remove laminas/laminas-psr7bridge`
  - `composer require laminas/laminas-mvc-middleware:~1.0.0`
  - Enable `Laminas\Mvc\Middleware` module in the application

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
