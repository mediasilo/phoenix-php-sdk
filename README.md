![MediaSilo](https://s3.amazonaws.com/mediasilo-phoenix-php-sdk/mediasilo-logo.jpg) 
PHP SDK
===============
#### Overview

MediaSilo makes it easy to share and collaborate with your team and customers anywhere. Check out our full feature set [here](https://www.mediasilo.com/features.php)!

The SDK is built on top of our REST API. To learn more about our REST API have a look at our documentation [here](http://developers.mediasilo.com/).

#### Requirements

The SDK is most easily used with [Composer](https://getcomposer.org). To install Composer:
```bash
cd <YOUR PROJECT ROOT>
curl -sS https://getcomposer.org/installer | php
```   

Check to see that composer has been installed:
```php

    php composer.phar --version
```

Install the SDK's dependencies:
```php

    php composer.phar install
```

#### Install the SDK into your project

In the root of your project create a composer.json as follows. More info on getting started with composer can be found [here](https://getcomposer.org/doc/00-intro.md). 

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mdelano/oauth-php"
        }
    ],
    "require" : {
        "mediasilo/phoenix-php-sdk": "0.7.6
    }
}
```

Next, install the SDK using
```shell
composer install
```
#### What can I do?
The MediaSiloAPI file is a wrapper to everything that the SDK can do for you. Refer to this file any time you want to see what functions are available to you.

![alt MediaSilo API Methods](https://s3.amazonaws.com/mediasilo-phoenix-php-sdk/apiMenu.jpg)
#### Sample Usage

```php
<?php

require_once('vendor/autoload.php');

use mediasilo\MediaSiloAPI;

// Set your credentials
$username = "PoohBear";
$password = "T!gger!sPushy";
$host = "100acreforest";

// Instantiate client
try {
    $mediaSiloAPI = MediaSiloAPI::createFromHostCredentials($username, $password, $host);
}
catch(\mediasilo\http\exception\NotAuthenticatedException $e) {
    print "Bad credentials. Cat on the keyboard? \n";
    exit;
}

// Start making some calls
$me = $mediaSiloAPI->me();
```
#### An Example

##### Getting Assets By Folder

```php
... Initialize the API as shown above

// Here's the project we're interested in traversing
$projectId = "07706DCC-014B-2CE0-CF518D31A23C393E";

// Let's find everything at the root of the projects
$rootLevelAssets = $mediaSiloApi->getAssetsByProject($projectId);
$rootLevelFolders = $mediaSiloApi->getProjectFolders($projectId);

// Ok, now let's traverse the prject to find the rest of the assets
foreach($rootLevelFolders as $folder) {
    get_folder_contents($mediaSiloApi, $folder->id);
}

function get_folder_contents($mediaSiloApi, $folderId) {
    print "FolderId:".$folderId."\n";
    try {
        $assets = $mediaSiloApi->getAssetsByFolder($folderId);
        var_dump($assets);
    }
    catch(NotFoundException $e) {
        print "There are not assets in this folder. Better get cracking and add some! \n";
    }

    try {
        $subfolders = $mediaSiloApi->getSubfolders($folderId);

        foreach($subfolders as $subfolder) {
            get_folder_contents($mediaSiloApi, $subfolder->id);
        }
    }
    catch(NotFoundException $e) {
        print "No more folders here!";
    }
}
```
