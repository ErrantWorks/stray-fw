# strayFw

[![Build Status](https://travis-ci.org/RocknRoot/strayFw.png?branch=master)](https://travis-ci.org/RocknRoot/strayFw)

strayFw is a PHP framework trying to be modern without following fashion, between full-featured frameworks and micro-frameworks.

beta - 0.4.4 - not ready for production yet

Code is free, new-BSD license. So... fork us !

## Requirements

* PHP >= 7.0
* mbstring extension
* For the Locale namespace, PECL intl extension >= 1.0.0

## Get started

    get composer
    $ php composer.phar create-project rocknroot/stray-fw-skeleton

## Addons

* [strayTwig](https://github.com/RocknRoot/strayTwig 'strayTwig'): [Twig](http://twig.sensiolabs.org/ 'Twig') rendering

## Need help ?

You can add an issue on github ! ;)

## Contribute

### Technical considerations

* The framework follows these standards :
    * [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md 'PSR-0')
    * [PSR-1](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md 'PSR-1')
    * [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md 'PSR-2')
    * [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md 'PSR-3')
    * [PSR-4](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md 'PSR-4')

### Static analysis

    $ ./vendor/bin/phan

### Coding standards

    $ curl -L http://cs.sensiolabs.org/download/php-cs-fixer-v2.phar -o php-cs-fixer.phar
    $ php php-cs-fixer.phar fix src/RocknRoot/StrayFw --rules=@PSR2,no_trailing_comma_in_singleline_array,no_singleline_whitespace_before_semicolons,cast_spaces,no_unused_imports,no_whitespace_in_blank_line,ordered_imports
