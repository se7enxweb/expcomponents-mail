# expcomponents-mail

The component allows you construct and/or parse Mail messages conforming to  the mail standard. It has support for attachments, multipart messages and HTML  mail. It also interfaces with SMTP to send mail or IMAP, POP3 or mbox to  retrieve e-mail.

This is the Exponential fork of [zetacomponents/Mail](https://github.com/zetacomponents/Mail),
maintained by 7x (se7en.x) and the Exponential Foundation for Exponential and anyone else
who uses the Zeta Components Mail component on current PHP versions. All credit for the
component goes to the Zeta Components authors listed in `composer.json` and `CREDITS`;
the upstream copyright notices (`NOTICE`) and the Apache License 2.0 (`LICENSE.txt`)
are kept unchanged.

## What is different from upstream

- The package is `se7enxweb/expcomponents-mail` and replaces `zetacomponents/mail`,
  so a project that requires the upstream package gets this one in its place.
- It requires the Exponential forks of the components it builds on (`se7enxweb/expcomponents-base`),
  which replace their upstream packages in the same way.
- The class names (`ezc*`) and the API are unchanged.
- Changes made in this fork are listed in `ChangeLog`.

## Installation

```bash
composer require se7enxweb/expcomponents-mail
```

## Tests

```bash
composer update
vendor/bin/phpunit
```

The suite runs on every push for PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5.

## License

Apache License 2.0, see `LICENSE.txt` and `NOTICE`.
