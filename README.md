![Logo](https://s3-eu-west-1.amazonaws.com/org.paraio/para.png)

# PHP Client for Para
[![Packagist](https://img.shields.io/packagist/v/erudika/para-client-php.svg)](https://packagist.org/packages/erudika/para-client-php)
[![Join the chat at https://gitter.im/Erudika/para](https://badges.gitter.im/Erudika/para.svg)](https://gitter.im/Erudika/para?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## What is this?

**Para** was designed as a simple and modular backend framework for object persistence and retrieval.
It helps you build applications faster by taking care of the backend. It works on three levels -
objects are stored in a NoSQL data store or any old relational database, then automatically indexed
by a search engine and finally, cached.

This is the PHP client for Para.

### Quick start

1. Use [Composer](https://getcomposer.org/doc/00-intro.md) and include `para-client-php` as a dependency.
If you don't use Composer, you can copy the `index.php` file and the `src` directory to your project.
2. Add `erudika/para-client-php` to your `composer.json` file:
```json
  "require": {
      "erudika/para-client-php": "1.*"
  }
```
3. Initialize the client with your access and secret API keys.
```php
$client = new \Para\ParaClient('ACCESS_KEY', 'SECRET_KEY');
```

## Documentation

### [Read the Docs](https://paraio.org/docs)

## Contributing

1. Fork this repository and clone the fork to your machine
2. Create a branch (`git checkout -b my-new-feature`)
3. Implement a new feature or fix a bug and add some tests
4. Commit your changes (`git commit -am 'Added a new feature'`)
5. Push the branch to **your fork** on GitHub (`git push origin my-new-feature`)
6. Create new Pull Request from your fork

For more information see [CONTRIBUTING.md](https://github.com/Erudika/para/blob/master/CONTRIBUTING.md)

## License
[Apache 2.0](LICENSE)
