Server-Side GA Content Experiments
===========================================================================================

While this is a standalone library, you might also be interested in the companion project
for general server-side Google Analytics tracking:
[php-ga](https://github.com/thomasbachem/php-ga).


About
-------------------------------------------------------------------------------------------

php-gacx is basically Google's [cx/api.js](https://developers.google.com/analytics/devguides/collection/gajs/experiments#cxjs)
in PHP: A server-side implementation that allows you to control and implement
[Content Experiments](https://developers.google.com/analytics/devguides/platform/features/experiments-overview)
entirely on the server.

This is done by parsing experiment data to make use of GA's multi-armed bandit algorithm
as well as programmatically modifying the "\_\_utmx" and "\_\_utmxx" cookies.

This does however depend on Google Analytics being used in the browser, see "Usage" below.

This library might become obsolete as soon as Google implements Content Experiments into
their [Measurement Protocol](https://developers.google.com/analytics/devguides/collection/protocol/v1/).


Requirements
-------------------------------------------------------------------------------------------

Requires PHP 5.3+ as namespaces and closures are used. Has no other dependencies and can
be used independantly from any framework or whatsoever environment.


Usage Example
-------------------------------------------------------------------------------------------

All methods match the ones from the JS API, so using php-gacx is pretty straightforward if
you've experience with [cx/api.js](https://developers.google.com/analytics/devguides/collection/gajs/experiments#cxjs):

```php
use UnitedPrototype\GoogleAnalytics;

$experiment = new GoogleAnalytics\Experiment('reBreiK2QpOws-pJlkla1o');

$variation = $experiment->chooseVariation();
```

In order to have the experiment data transferred to Google Analytics, you need to use
Google Analytics on the client side either via the traditional `ga.js` or Google's new
`analytics.js` (Universal Analytics).

`ga.js` will work out of the box, as it simply consider and include the "\_\_utmx"
cookie value when sending tracking data to Google Analytics.

`analytics.js` does sadly no longer consider the "\_\_utmx" cookie. You will therefore
have to tell it via Javascript which variation of an experiment has been chosen, and
it's limited to one experiment per page. Example:
```js
// Hand over experiment data to analytics.js, as it ignores the "__utmx" cookie
window.gaData = {
	expId:  '<?= $experiment->getId(); ?>',
	expVar: '<?= $experiment->getChosenVariation(); ?>'
};
```
The advantage of using php-gacx here is still that you can choose the variation on the
server-side while still making use of the multi-armed bandit algorithm considering the
variation's different weights.


Disclaimer
-------------------------------------------------------------------------------------------

Google Analytics is a registered trademark of Google Inc.