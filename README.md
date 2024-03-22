## Description

This is a compatibility module for [Magento2AttributeLanding](https://github.com/Tweakwise/Magento2AttributeLanding) and [Magento2Tweakwise](https://github.com/Tweakwise/Magento2Tweakwise).
It will install the following packages: 
1. tweakwise/tweakwise
2. tweakwise/tweakwise-export
3. tweakwise/magento2-attributelanding
4. tweakwise/magento2-attributelanding-tweakwise (this package)

Packages tweakwise/tweakwise and tweakwise/tweakwise-export provides magento2 integration with the Tweakwise navigator (https://www.tweakwise.com/)
Package tweakwise/magento2-attributelanding provides magento2 support for landingspages based on attributes and categories for example "red-pants", here category is "pants" and the attribute is color with value red.
This package provides the integration between the navigator and the landingspages.


## Installation
Install package using composer
```sh
composer require tweakwise/magento2-attributelanding-tweakwise
```

Run installers
```sh
php bin/magento setup:upgrade
```

## A note on navigation.
It is possible to have your users navigate to a landingpage when the user happens to select a set of filters which matches a landingpage.
In order to do this one has to enable "tweakwise_attributelanding/general/allow_crosslink".
It is also important to note that in order to achieve this the filter values configured in the landingpage match the tweakwise filter values (as known in the navigator) exactly, this is case sensitive!

## Contributors
If you want to create a pull request as a contributor, use the guidelines of semantic-release. semantic-release automates the whole package release workflow including: determining the next version number, generating the release notes, and publishing the package.
By adhering to the commit message format, a release is automatically created with the commit messages as release notes. Follow the guidelines as described in: https://github.com/semantic-release/semantic-release?tab=readme-ov-file#commit-message-format.
