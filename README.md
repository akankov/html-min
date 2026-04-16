[![Build Status](https://github.com/akankov/html-min/actions/workflows/ci.yml/badge.svg?branch=rebrand/v1)](https://github.com/akankov/html-min/actions)
[![Latest Stable Version](https://poser.pugx.org/akankov/html-min/v/stable)](https://packagist.org/packages/akankov/html-min)
[![Total Downloads](https://poser.pugx.org/akankov/html-min/downloads)](https://packagist.org/packages/akankov/html-min)
[![License](https://poser.pugx.org/akankov/html-min/license)](https://packagist.org/packages/akankov/html-min)

# HtmlMin: HTML Compressor and Minifier for PHP

> Maintained fork of [voku/HtmlMin](https://github.com/voku/HtmlMin). See [UPGRADE-FROM-VOKU.md](UPGRADE-FROM-VOKU.md) for migration notes.

### Description

HtmlMin is a fast, easy-to-use PHP library that minifies HTML5 source by removing extra whitespace, comments, and unneeded characters without breaking content structure. It also prepares HTML for better gzip results by sorting attributes and CSS class names.

**Supported PHP versions:** 8.3, 8.4, 8.5. If you need PHP 7.x or 8.0–8.2 runtime support, use upstream `voku/html-min:^4.5`.

### Install via "composer require"

```shell
composer require akankov/html-min
```

### Quick Start

```php
use Akankov\HtmlMin\HtmlMin;

$html = "
<html>
  \r\n\t
  <body>
    <ul style=''>
      <li style='display: inline;' class='foo'>
        \xc3\xa0
      </li>
      <li class='foo' style='display: inline;'>
        \xc3\xa1
      </li>
    </ul>
  </body>
  \r\n\t
</html>
";
$htmlMin = new HtmlMin();

echo $htmlMin->minify($html); 
// '<html><body><ul><li class=foo style="display: inline;"> à <li class=foo style="display: inline;"> á </ul>'
```

### Options

```php
use Akankov\HtmlMin\HtmlMin;

$htmlMin = new HtmlMin();

/* 
 * Protected HTML (inline css / inline js / conditional comments) are still protected,
 *    no matter what settings you use.
 */

$htmlMin->doOptimizeViaHtmlDomParser();               // optimize html via "HtmlDomParser()"
$htmlMin->doRemoveComments();                         // remove default HTML comments (depends on "doOptimizeViaHtmlDomParser(true)")
$htmlMin->doSumUpWhitespace();                        // sum-up extra whitespace from the Dom (depends on "doOptimizeViaHtmlDomParser(true)")
$htmlMin->doRemoveWhitespaceAroundTags();             // remove whitespace around tags (depends on "doOptimizeViaHtmlDomParser(true)")
$htmlMin->doOptimizeAttributes();                     // optimize html attributes (depends on "doOptimizeViaHtmlDomParser(true)")
$htmlMin->doRemoveHttpPrefixFromAttributes();         // remove optional "http:"-prefix from attributes (depends on "doOptimizeAttributes(true)")
$htmlMin->doRemoveHttpsPrefixFromAttributes();        // remove optional "https:"-prefix from attributes (depends on "doOptimizeAttributes(true)")
$htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(); // keep "http:"- and "https:"-prefix for all external links 
$htmlMin->doMakeSameDomainsLinksRelative(['example.com']); // make some links relative, by removing the domain from attributes
$htmlMin->doRemoveDefaultAttributes();                // remove defaults (depends on "doOptimizeAttributes(true)" | disabled by default)
$htmlMin->doRemoveDeprecatedAnchorName();             // remove deprecated anchor-jump (depends on "doOptimizeAttributes(true)")
$htmlMin->doRemoveDeprecatedScriptCharsetAttribute(); // remove deprecated charset-attribute - the browser will use the charset from the HTTP-Header, anyway (depends on "doOptimizeAttributes(true)")
$htmlMin->doRemoveDeprecatedTypeFromScriptTag();      // remove deprecated script-mime-types (depends on "doOptimizeAttributes(true)")
$htmlMin->doRemoveDeprecatedTypeFromStylesheetLink(); // remove "type=text/css" for css links (depends on "doOptimizeAttributes(true)")
$htmlMin->doRemoveDeprecatedTypeFromStyleAndLinkTag(); // remove "type=text/css" from all links and styles
$htmlMin->doRemoveDefaultMediaTypeFromStyleAndLinkTag(); // remove "media="all" from all links and styles
$htmlMin->doRemoveDefaultTypeFromButton();            // remove type="submit" from button tags 
$htmlMin->doRemoveEmptyAttributes();                  // remove some empty attributes (depends on "doOptimizeAttributes(true)")
$htmlMin->doRemoveValueFromEmptyInput();              // remove 'value=""' from empty <input> (depends on "doOptimizeAttributes(true)")
$htmlMin->doSortCssClassNames();                      // sort css-class-names, for better gzip results (depends on "doOptimizeAttributes(true)")
$htmlMin->doSortHtmlAttributes();                     // sort html-attributes, for better gzip results (depends on "doOptimizeAttributes(true)")
$htmlMin->doRemoveSpacesBetweenTags();                // remove more (aggressive) spaces in the dom (disabled by default)
$htmlMin->doRemoveOmittedQuotes();                    // remove quotes e.g. class="lall" => class=lall
$htmlMin->doRemoveOmittedHtmlTags();                  // remove ommitted html tags e.g. <p>lall</p> => <p>lall 
```

PS: you can use the "nocompress"-tag to keep the html e.g.: "<nocompress>\n foobar \n</nocompress>"

### Unit Test

1) [Composer](https://getcomposer.org) is a prerequisite for running the tests.

```
composer require voku/html-min
```

2) The tests can be executed by running this command from the root directory:

```bash
./vendor/bin/phpunit
```

### Support

For support and donations please visit [Github](https://github.com/voku/HtmlMin/) | [Issues](https://github.com/voku/HtmlMin/issues) | [PayPal](https://paypal.me/moelleken) | [Patreon](https://www.patreon.com/voku).

For status updates and release announcements please visit [Releases](https://github.com/voku/HtmlMin/releases) | [Twitter](https://twitter.com/suckup_de) | [Patreon](https://www.patreon.com/voku/posts).

For professional support please contact [me](https://about.me/voku).

### Thanks

- Thanks to [GitHub](https://github.com) (Microsoft) for hosting the code and a good infrastructure including Issues-Managment, etc.
- Thanks to [IntelliJ](https://www.jetbrains.com) as they make the best IDEs for PHP and they gave me an open source license for PhpStorm!
- Thanks to [Travis CI](https://travis-ci.com/) for being the most awesome, easiest continous integration tool out there!
- Thanks to [StyleCI](https://styleci.io/) for the simple but powerful code style check.
- Thanks to [PHPStan](https://github.com/phpstan/phpstan) & [Psalm](https://github.com/vimeo/psalm) for really great Static analysis tools and for discovering bugs in the code!
