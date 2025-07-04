[![](https://img.shields.io/packagist/v/inspiredminds/contao-future-cache-invalidation.svg)](https://packagist.org/packages/inspiredminds/contao-future-cache-invalidation)
[![](https://img.shields.io/packagist/dt/inspiredminds/contao-future-cache-invalidation.svg)](https://packagist.org/packages/inspiredminds/contao-future-cache-invalidation)

Contao Future Cache Invalidation
================================

Invalidates cache tags in the future for any DCA that has a `start` or `stop` field.

In Contao **5** you will only need to route the message manually:

```yaml
# config/config.yaml
framework:
    messenger:
        routing:
            'InspiredMinds\ContaoFutureCacheInvalidation\Message\InvalidateCacheMessage': contao_prio_high
```

In Contao **4.13** you will also have to create a messenger transport, e.g.:

```yaml
framework:
    messenger:
        transports:
            cache_invalidation: 'doctrine://default?queue_name=cache_invalidation'
        routing:
            'InspiredMinds\ContaoFutureCacheInvalidation\Message\InvalidateCacheMessage': cache_invalidation
```

If you use such a `doctrine://` transport you will also have to install `symfony/doctrine-messenger`:

```
composer require symfony/doctrine-messenger
```

Then you have to consume the messages somehow via

```
vendor/bin/contao-console messenger:consume cache_invalidation
```

e.g. via a `crontab` entry like this:

```
* * * * * /usr/bin/php /var/www/example.com/vendor/bin/contao-console messenger:consume cache_invalidation --time-limit=59 --quiet
```

There is still a caveat: the cache invalidation is based on the `contao.db.*.*` cache tags. However, this will not
work for the `start` case, as the cache tag would be missing for that URL. For child elements like articles, content
elements or news (child of a news archive) it will still work as the extension will also invalidate the tags of the
parent. But for pages for example this would not be solved this way - i.e. when you have a `start` time for page, it
would still not show up in the menu of cached pages. In this case the extension clears the _whole_ cache instead.

_Note:_ as this utilizes the Symfony Messenger with its `DelayStamp` it will only work for the following messenger
transport types:

* `doctrine`
* `amqp`
* `redis`

There is also a command with which you can create these future cache invalidation messages for all existing database
entries after installation:

```
vendor/bin/contao-console contao_future_cache_invalidation:create-messages
```
