# simplesamlphp-module-affiliation

A suite of SimpleSAMLphp authentication processing filters for processing
attributes expressing affiliation information.

## PrimaryAffiliation

SimpleSAMLphp authproc filter for extracting the user's primary affiliation.
 
The filter generates a primary affiliation (`eduPersonPrimaryAffiliation`)
value based on the affiliation information contained in the scoped
affiliation attribute (`eduPersonScopedAffiliation` - `ePSA`) value(s).
Specifically, in the presence of a single-valued `ePSA` attribute,
the primary affiliation is derived from the affiliation value contained in
that `ePSA` attribute. In the case of a multi-valued `ePSA` attribute, the
filter assigns the `"member"` affiliation for one or more of the following
affiliations:

* `"faculty"` or
* `"staff"` or
* `"student"` or
* `"employee"`

The module assumes that:
1. the `eduPersonScopedAffiliation` attribute name is expressed as:
   `"urn:oid:1.3.6.1.4.1.5923.1.1.1.9"`
2. the `eduPersonPrimaryAffiliation` attribute name is expressed as:
   `"urn:oid:1.3.6.1.4.1.5923.1.1.1.5"`

### Configuration

The following configuration options are available:

* `blacklist`: Optional, an array of SP entityIDs that should be excluded
  from this authproc filter

#### Example configuration

```php
'authproc' => [
    ...
    '101' => [
        'class' => 'affiliation:PrimaryAffiliation',
        // Optional list of SP entity IDs that should be excluded
        'blacklist' => [
            'https://sp1.example.org',
            'https://sp2.example.org',
        ],
    ],
],
```

## Compatibility matrix

This table matches the module version with the supported SimpleSAMLphp version.

| Module |  SimpleSAMLphp |
|:------:|:--------------:|
| v1.0   | v1.14          |

## License

Licensed under the Apache 2.0 license, for details see `LICENSE`.
