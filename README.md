# Chimera - DI Symfony

[![Total Downloads](https://img.shields.io/packagist/dt/chimera/di-symfony.svg?style=flat-square)](https://packagist.org/packages/chimera/di-symfony)
[![Latest Stable Version](https://img.shields.io/packagist/v/chimera/di-symfony.svg?style=flat-square)](https://packagist.org/packages/chimera/di-symfony)
[![Unstable Version](https://img.shields.io/packagist/vpre/chimera/di-symfony.svg?style=flat-square)](https://packagist.org/packages/chimera/di-symfony)

![Branch master](https://img.shields.io/badge/branch-master-brightgreen.svg?style=flat-square)
[![Build Status](https://img.shields.io/travis/com/chimeraphp/di-symfony/master.svg?style=flat-square)](http://travis-ci.com/chimeraphp/di-symfony)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/chimeraphp/di-symfony/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/chimeraphp/di-symfony/?branch=master)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/chimeraphp/di-symfony/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/chimeraphp/di-symfony/?branch=master)

> The term Chimera (_/kɪˈmɪərə/_ or _/kaɪˈmɪərə/_) has come to describe any
mythical or fictional animal with parts taken from various animals, or to
describe anything composed of very disparate parts, or perceived as wildly
imaginative, implausible, or dazzling.

There are many many amazing libraries in the PHP community and with the creation
and adoption of the PSRs we don't necessarily need to rely on full stack
frameworks to create a complex and well designed software. Choosing which
components to use and plugging them together can sometimes be a little
challenging.

The goal of this set of packages is to make it easier to do that (without
compromising the quality), allowing you to focus on the behaviour of your
software.

This package provides creates the configuration of the dependency injection
container based on the packages you have required in your application. By
relying on `symfony/dependency-injection` we put the complexity of wiring the
components together in compilation time (instead of runtime).

There's a lot of hidden complexity in this wiring process, which definitely affects
the organisation of the compiler passes, but the reason for that is to ensure that
only things related to your software gets executed when handling requests.

## Installation

Package is available on [Packagist](http://packagist.org/packages/chimera/di-symfony),
you can install it using [Composer](http://getcomposer.org).

```shell
composer require chimera/di-symfony
```

### PHP Configuration

In order to make sure that we're dealing with the correct data, we're using `assert()`,
which is a very interesting feature in PHP but not often used. The nice thing
about `assert()` is that we can (and should) disable it in production mode so
that we don't have useless statements.

So, for production mode, we recommend you to set `zend.assertions` to `-1` in your `php.ini`.
For development you should leave `zend.assertions` as `1` and set `assert.exception` to `1`, which
will make PHP throw an [`AssertionError`](https://secure.php.net/manual/en/class.assertionerror.php)
when things go wrong.

Check the documentation for more information: https://secure.php.net/manual/en/function.assert.php

## Usage

Symfony DI component is just amazing and it has everything we need to compile the
container and just load it from a set of generated files, but the control of when
to update those files now lies in the Kernel/MicroKernel. However we don't necessarily
need to have a Kernel controlling that, we can use [`lcobucci/di-builder`](http://packagist.org/packages/lcobucci/di-builder)
and simply get a Symfony DI container. That's what this package uses under the
hood to create the services.

## License

MIT, see [LICENSE file](https://github.com/chimeraphp/di-symfony/blob/master/LICENSE).
