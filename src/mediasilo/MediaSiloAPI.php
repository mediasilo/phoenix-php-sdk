<?php

namespace mediasilo;

if (!function_exists('curl_init')) {
    throw new \Exception('We need cURL for the API to work. Have a look here: http://us3.php.net/curl');
}

if (!function_exists('json_decode')) {
    throw new \Exception('We need json_decode for the API to work. If you\'re running a linux distro install this package: php-pecl-json');
}

use mediasilo\http\exception\NotFoundException;
use mediasilo\http\oauth\OAuthException;
use mediasilo\http\MediaSiloResourcePaths;
use mediasilo\project\ProjectProxy;
use mediasilo\http\WebClient;
use mediasilo\favorite\FavoriteProxy;
use mediasilo\project\Project;
use mediasilo\quicklink\Configuration;
use mediasilo\quicklink\QuickLink;
use mediasilo\quicklink\QuickLinkProxy;
use mediasilo\quicklink\QuickLinkCommentProxy;
use mediasilo\account\AccountPreferencesProxy;
use mediasilo\asset\AssetProxy;
use mediasilo\channel\ChannelProxy;
use mediasilo\channel\Channel;
use mediasilo\comment\Comment;
use mediasilo\share\Share;
use mediasilo\share\ShareProxy;
use mediasilo\share\email\EmailRecipient;
use mediasilo\share\email\EmailShare;
use mediasilo\transcript\TranscriptProxy;
use mediasilo\transcript\TranscriptServiceProxy;
use mediasilo\quicklink\Setting;
use mediasilo\http\oauth\TwoLeggedOauthClient;
use mediasilo\quicklink\analytics\QuickLinkAnalyticsProxy;
use mediasilo\user\UserProxy;
use mediasilo\user\UserPreferencesProxy;
use mediasilo\user\User;
use mediasilo\user\PasswordReset;
use mediasilo\user\PasswordResetRequest;

/******************************************************************************************
 * MediaSiloAPI
 *
 * This is the API client for the MediaSIlo REST API.
 *
 * Created By: Mike Delano
 * Created On: 07/17/2014
 *
 * Copyright 2014 MediaSilo
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 ******************************************************************************************/
class MediaSiloAPI
{
    protected $webClient;
    protected $favoriteProxy;
    protected $projectProxy;
    protected $quicklinkProxy;
    protected $quicklinkAnalyticsProxy;
    protected $shareProxy;
    protected $assetProxy;
    protected $channelProxy;
    protected $transcriptProxy;
    protected $transcriptServiceProxy;
    protected $accountPreferencesProxy;
    protected $userProxy;
    protected $userPreferencesProxy;
    protected $consumerKey;
    protected $consumerSecret;
    protected $baseUrl;

    protected $me;

    public function __construct() {}

    protected function init() {
        $this->proxyInit();
        $this->me();
    }

    protected function proxyInit() {
        $this->favoriteProxy = new FavoriteProxy($this->webClient);
        $this->projectProxy = new ProjectProxy($this->webClient);
        $this->quicklinkProxy = new QuickLinkProxy($this->webClient);
        $this->shareProxy = new ShareProxy($this->webClient);
        $this->assetProxy = new AssetProxy($this->webClient);
        $this->channelProxy = new ChannelProxy($this->webClient);
        $this->transcriptProxy = new TranscriptProxy($this->webClient);
        $this->transcriptServiceProxy = new TranscriptServiceProxy($this->webClient);
        $this->quicklinkAnalyticsProxy = new QuickLinkAnalyticsProxy($this->webClient);
        $this->accountPreferencesProxy = new AccountPreferencesProxy($this->webClient);
        $this->userProxy = new UserProxy($this->webClient);
        $this->userPreferencesProxy = new UserPreferencesProxy($this->webClient);
    }

    /**
     * Creates an instance of MediaSilo API using username, password, and hostname
     *
     * @param $username
     * @param $password
     * @param $hostname
     * @param $baseUrl
     * @return MediaSiloAPI
     */
    public static function createFromHostCredentials($username, $password, $hostname, $baseUrl = Meta::API_ROOT_URL) {
        $instance = new self();
        $instance->webClient = WebClient::createFromHostCredentials($username, $password, $hostname, $baseUrl);
        $instance->init();
        $instance->me();
        return $instance;
    }

    public static function createFromSession($session, $host, $baseUrl = "phoenix.mediasilo.com/v3") {
        $instance = new self();
        $instance->webClient = WebClient::createFromSession($session, $host, $baseUrl);
        $instance->init();

        return $instance;
    }

    /******************************************************************************************
     * Preferences
     *
     * Preferences are used for various configuration and customization for your account and user.
     * Using the following methods you can retrieve existing preferences as well as set preferences.
     *
     * Example Preference Object
     *
     *  {
     *      "id": 1234,
     *      "accountId": "1234ABCD",
     *      "name": "something_i_prefer",
     *      "value": "true"
     *  }
     ******************************************************************************************/

    /**
     * Get all preferences defined for your account
     *
     * @return mixed
     */
    public function getAccountPreferences() {
        return $this->accountPreferencesProxy->getPreferences($this->me->accountId);
    }

    /**
     * Get a single preference that already exists for your account
     * @param $preferenceKey The name of the preference that you'd like to get
     * @return mixed
     */
    public function getAccountPreference($preferenceKey) {
        return $this->accountPreferencesProxy->getPreference($this->me->accountId, $preferenceKey);
    }

    /**
     * Update a single account preference
     * @param $preferenceKey The name of the preference that will be changed
     * @param $preferenceValue The value to set the preference to
     * @return mixed
     */
    public function updateMyAccountPreference($preferenceKey, $preferenceValue) {
        return $this->accountPreferencesProxy->updatePreference($this->me->accountId, $preferenceKey, $preferenceValue);
    }

    /**
     * Get all preferences defined for ANY user.
     *
     * NOTE: This requires permission to read about another user
     *
     * @param $userId
     * @return mixed
     */
    public function getUserPreferencesForUser($userId) {
        return $this->userPreferencesProxy->getPreferences($userId);
    }

    /**
     * Get all preferences defined for you
     * @return mixed
     */
    public function getUserPreferences()
    {
        return $this->userPreferencesProxy->getPreferences($this->me->id);
    }

    /**
     * Get a single preference that already exists for you
     * @param $preferenceKey The name of the preference that you'd like to get
     * @return mixed
     */
    public function getUserPreference($preferenceKey)
    {
        return $this->userPreferencesProxy->getPreference($this->me->id, $preferenceKey);
    }

    /**
     * Update one of you user preferences
     * @param $preferenceKey The name of the preference that will be changed
     * @param $preferenceValue The value to set the preference to
     * @return mixed
     */
    public function updateMyPreference($preferenceKey, $preferenceValue) {
        return $this->userPreferencesProxy->updatePreference($this->me->id, $preferenceKey, $preferenceValue);
    }

    /**
     * Update a preference for another user
     *
     * NOTE: This requires permission to modify another user
     *
     * @param $userId The identifier of the users whoe preference will be modified
     * @param $preferenceKey The name of the preference that will be changed
     * @param $preferenceValue The value to set the preference to
     * @return mixed
     */
    public function updateUserPreference($userId, $preferenceKey, $preferenceValue) {
        return $this->userPreferencesProxy->updatePreference($userId, $preferenceKey, $preferenceValue);
    }










    /******************************************************************************************
     * Projects
     *
     * http://developers.mediasilo.com/projects
     *
     * Projects are the root container for assets and folders, they are also how you control
     * which assets users have access to. A user must be assigned to a project in order to access
     * the assets and folders within it OR they must be an administrator from which they can access
     * any project.
     *
     * Example Project Object
     *
     *  {
     *      "id": "12341234ABCD-12AB-1234-1234ABCD1234ABCD",
     *      "numericId": 987654321,
     *      "name": "My New Project",
     *      "description": "This project will hold the best assets we have",
     *      "dateCreated": 1224190488000,
     *      "ownerId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *      "folderCount": 2,
     *      "favorite": false
     *  }
     ******************************************************************************************/

    /**
     * Creates a new project in your MediaSilo account
     *
     * @param Project $project
     */
    public function createProject(Project $project)
    {
        $this->projectProxy->createProject($project);
    }

    /**
     * Gets an existing project.
     *
     * @param $id
     * @return Project
     */
    public function getProject($id)
    {
        return $this->projectProxy->getProject($id);
    }

    /**
     * Gets a list of Projects
     *
     * @return Array[Project]
     */
    public function getProjects() {
        return $this->projectProxy->getProjects();
    }

    /**
     * Get another user projects
     *
     * NOTE: You must have permission to read about another user
     *
     * @param $userId
     * @return mixed
     */
    public function getUsersProjects($userId)
    {
        return $this->projectProxy->getUsersProjects($userId);
    }

    /**
     * Updates an existing project. ID must be a valid project Id.
     *
     * @param Project $project
     */
    public function updateProject(Project $project)
    {
        $this->projectProxy->updateProject($project);
    }

    /**
     * Deletes an existing project from a given project Id.
     *
     * NOTE: The project must be empty before deletion can be invoked
     *
     * @param $id
     */
    public function deleteProject($id)
    {
        $this->projectProxy->deleteProject($id);
    }

    /**
     * Copies the structure and users of an existing project to a new project
     *
     * @param $projectId
     * @return mixed
     */
    public function cloneProject($projectId)
    {
        return $this->projectProxy->cloneProject($projectId);
    }

    /**
     * Set a given project as one of your favorites
     *
     * @param $projectId
     */
    public function favorProject($projectId)
    {
        $this->favoriteProxy->favorProject($projectId);
    }

    /**
     * Remove a given project from you list of favorites
     *
     * @param $projectId
     */
    public function unfavorProject($projectId)
    {
        $this->favoriteProxy->unfavor($projectId);
    }

    /**
     * Get all of your favorite projects
     *
     * @return array
     */
    public function getFavoriteProjects()
    {
        return $this->favoriteProxy->getFavoriteProjects();
    }

    /**
     * Gets a list of Project's Sub-folders
     *
     * @param $projectId
     * @return Array[Object]
     */
    public function getProjectFolders($projectId)
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::PROJECT_FOLDERS, $projectId);
        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }










    /******************************************************************************************
     * Folders
     *
     * http://developers.mediasilo.com/folders
     *
     * Folders are containers for assets that can exist in a project or in another folder. Users
     * who have access to the root project the folder exists in will automatically have access to
     * the folder, permission can not be controlled on a folder by folder basis.
     *
     * Folders are structured in a hierarchy, a folder will either exist in the root of a project
     * or in another folder, you can only retrieve a single layer of the hierarchy at a time. The
     * way you retrieve the first layer of the hierarchy, or the folders in the root of a project,
     * is different then when you request folders that are contained with in another folder.
     *
     * Each folder object will have the projectId of the project it belongs to, even if the folder
     * is contained in another folder. If the folder is contained with in another folder it will
     * also have a parentId, which is the Id of the folder it exists in, if the folder belongs to
     * a project and is not in another folder the parentId will be 0.
     *
     * Example Folder Object
     *
     *  {
     *      "id": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *      "name": "Flying Balloons",
     *      "parentId": null,
     *      "parentNumericId": null,
     *      "projectId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *      "numericId": 1234,
     *      "folderCount": 1
     *  }
     ******************************************************************************************/

    /**
     * Get Folder By UUID
     * @param String $id
     * @return Object
     */
    public function getFolder($id)
    {
        $clientResponse = $this->webClient->get(MediaSiloResourcePaths::FOLDERS . "/" . $id);
        return json_decode($clientResponse->getBody());
    }

    /**
     * Gets a list of Folder's Sub-folders
     * @param String $parentFolderId
     * @return Array[Object]
     */
    public function getSubfolders($parentFolderId)
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::SUB_FOLDERS, $parentFolderId);
        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }










    /******************************************************************************************
     * Assets
     *
     * Assets are at the core of all MediaSilo applications. Every video and audio file, image,
     * document and archive that is uploaded is considered an Asset. Asset objects are database
     * records that refer to files that have been uploaded. Some of these objects are fairly simple,
     * while others (like videos) contain additional data about source files, proxies and playback
     * options.
     *
     * Example Asset Object
     *
     *  {
            "id": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
            "title": "videotest4.mov",
            "description": "",
            "fileName": "videotest4.mov",
            "projectId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
            "folderId": null,
            "uploadedBy": "simon",
            "approvalStatus": "none",
            "archiveStatus": "n/a",
            "transcriptStatus": "N/A",
            "type": "video",
            "dateCreated": 1396039017000,
            "dateModified": 1396039017000,
            "progress": 100,
            "commentCount": 0,
            "myRating": null,
            "averageRating": null,
            "permissions": [
                "collaboration.requestapproval",
                "collaboration.comment",
                "collaboration.rate",
                "asset.create",
                "asset.update",
                "asset.delete",
                "asset.source",
                "sharing.internal",
                "service.transcript",
                "reporting.export"
            ],
            "derivatives": [
                {
                    "type": "source",
                    "url": "https://1234ABCD.cloudfront.net/1234ABCD/1234ABCD-12AB-1234-1234ABCD1234ABCD.mov",
                    "fileSize": 5696,
                    "height": 360,
                    "width": 640,
                    "duration": 262060
                },
                {
                    "type": "proxy",
                    "url": "https://1234ABCD.cloudfront.net/1234ABCD/1234ABCD-12AB-1234-1234ABCD1234ABCD.mp4",
                    "fileSize": 5696,
                    "height": 360,
                    "width": 640,
                    "duration": 262060,
                    "thumbnail": "https://s3.amazonaws.com/thumbnails.mediasilo.com/1234ABCD/1234ABCD-12AB-1234-1234ABCD1234ABCD_small.jpg",
                    "posterFrame": "https://s3.amazonaws.com/thumbnails.mediasilo.com/1234ABCD/1234ABCD-12AB-1234-1234ABCD1234ABCD_large.jpg",
                    "strategies": [
                        {
                            "type": "rtmp",
                            "url": "1234ABCD/1234ABCD-12AB-1234-1234ABCD1234ABCD.mov?Expires=1405530970&Signature=SOMESIGNATUREVALUE&Key-Pair-Id=SOMEKEYPAIRID",
                            "streamer": "rtmp://s37758y8jcblwg.cloudfront.net/cfx/st"
                        }
                    ]
                }
            ],
            "tags": awesome, wicked,
            "private": false,
            "external": false
        }
     ******************************************************************************************/

    /**
     * Gets an asset from an asset Id.
     * @param $id
     * @param $acl (if true the ACL for the requesting user will be attached to each asset)
     * @return Asset
     */
    public function getAsset($id, $acl = false)
    {
        return $this->assetProxy->getAsset($id, $acl);
    }

    /**
     * Gets assets based on an array of key value search queries
     * @param $searchParams
     * @param $acl (if true the ACL for the requesting user will be attached to each asset)
     * @return Assets
     */
    public function getAssets($searchParams, $acl = false)
    {
        return $this->assetProxy->getAssets($searchParams, $acl);
    }

    /**
     * Gets a list of assets from an array of asset Ids.
     * @param Array $ids
     * @param Boolean $acl (if true the ACL for the requesting user will be attached to each asset)
     * @return Array(Asset)
     */
    public function getAssetsByIds($ids, $acl = false) {
        return $this->assetProxy->getAssetByIds($ids, $acl);
    }

    /**
     * Gets assets in the given project
     * @param $projectId
     * @param $searchParams
     * @param $acl (if true the ACL for the requesting user will be attached to each asset)
     * @return Array(Asset)
     */
    public function getAssetsByProject($projectId, $acl = false, $searchParams = array())
    {
        return $this->assetProxy->getAssetsByProjectId($projectId, $acl, $searchParams);
    }

    /**
     * Gets assets in the given folder
     * @param $folderId
     * @param $searchParams
     * @param $acl (if true the ACL for the requesting user will be attached to each asset)
     * @return Array(Asset)
     */
    public function getAssetsByFolder($folderId, $acl = false, $searchParams = array())
    {
        return $this->assetProxy->getAssetsByFolderId($folderId, $acl, $searchParams);
    }










    /******************************************************************************************
     * Ratings
     *
     * http://developers.mediasilo.com/ratings
     *
     * Ratings enable users to rate assets on a scale from 0 to 5. All user ratings for a given
     * asset can be retrieved, and a user may modify their rating for a given asset if they have
     * permission to do so. A rating can not be deleted but it can be set to 0, the ratings
     * resource acts the same for both PUT and POST requests.
     *
     * Example Rating Object
     *
     *  {
     *      "ownerId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *      "dateCreated": 1405610017,
     *      "rating": 4,
     *  }
     ******************************************************************************************/

    /**
     * Get a list of Ratings by Asset UUID
     * @param String $assetId
     * @return Array[Object]
     */
    public function getAssetRatings($assetId)
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::ASSET_RATINGS, $assetId);
        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }










    /******************************************************************************************
     * Meta Data
     *
     * http://developers.mediasilo.com/metadata
     *
     * Metadata are key value pairs attached to a given asset that can store various kinds of
     * information. Metadata is always associated with an asset and can not exist on its own.
     *
     * Example Meta Data Object
     *
     *  {
     *      "type": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *      "key": Artist,
     *      "value": "Passenger",
     *      "valueType": "String",
     *      "createdBy": "1234ABCD-12AB-1234-1234ABCD1234ABCD"
     *  }
     ******************************************************************************************/

    /**
     * Gets an Asset's Meta Data entry by Key
     *
     * @param String $assetId
     * @param String $key
     * @return Object
     */
    public function getAssetMetaDatum($assetId, $key)
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::ASSET_METADATA, $assetId) . "/" . $key;
        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }

    /**
     * Gets a list of an asset's Meta Data
     *
     * @param String $assetId
     * @return Array
     */
    public function getAssetMetaData($assetId)
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::ASSET_METADATA, $assetId);
        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }










    /******************************************************************************************
     * Channels
     *
     * A channel is an embeddable collection of assets
     *
     * Example Channel Object
     *
     *  {
     *       "id": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *       "name": "My New Channel",
     *       "dateCreated": 1223956800000,
     *       "autoPlay": false,
     *       "playback": "PROGRESSIVE",
     *       "width": 656,
     *       "height": 438,
     *       "stretching": "exactfit",
     *       "assets": [
     *          "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *          "2234ABCD-12AB-1234-1234ABCD1234ABCD",
     *          "3234ABCD-12AB-1234-1234ABCD1234ABCD"
     *       ],
     *       "feeds": {
     *          "iTunes": "https://feeds.mediasilo.com/1234ABCD-12AB-1234-1234ABCD1234ABCD/mrss/itunes/",
     *          "Media RSS (Streaming)": "https://feeds.mediasilo.com/1234ABCD-12AB-1234-1234ABCD1234ABCD/mrss/true/",
     *          "Media RSS": "https://feeds.mediasilo.com/1234ABCD-12AB-1234-1234ABCD1234ABCD/mrss/"
     *       },
     *       "public": false
     *  }
     ******************************************************************************************/

    /**
     * Gets the channel for the given Id
     * @param $id
     * @return Channel
     */
    public function getChannel($id)
    {
        return $this->channelProxy->getChannel($id);
    }

    /**
     * Gets all channels user has access to
     * @return Array(Channel)
     */
    public function getChannels()
    {
        return $this->channelProxy->getChannels();
    }

    /**
     * Creates a new channel
     * @param $name,
     * @param $autoPlay
     * @param $height
     * @param $width
     * @param $playback
     * @param $public
     * @param $stretching
     * @param array $assets
     * @return Channel
     */
    public function createChannel($name, $autoPlay, $height, $width, $playback, $public, $stretching, array $assets) {
        $channel = new Channel(null, $name, null, $autoPlay, $height, $width, $playback, $public, $stretching, null, $assets);
        $this->channelProxy->createChannel($channel);

        return $channel;
    }

    /**
     * Updates a channel with the given Id
     * @param $id,
     * @param $name,
     * @param $autoPlay
     * @param $height
     * @param $width
     * @param $playback
     * @param $public
     * @param $stretching
     * @param array $assets
     * @return Channel
     */
    public function updateChannel($id, $name, $autoPlay, $height, $width, $playback, $public, $stretching, array $assets) {
        $channel = new Channel($id, $name, null, $autoPlay, $height, $width, $playback, $public, $stretching, null, $assets);
        $this->channelProxy->updateChannel($channel);

        return $channel;
    }

    /**
     * Deletes the given channel
     * @param $channelId
     */
    public function deleteChannel($channelId)
    {
        $this->channelProxy->deleteChannel($channelId);
    }










    /******************************************************************************************
     * Transcripts
     *
     * MediaSilo offers logging services for assets that include dialogue.
     *
     * Example Channel Object
     *
     *  {
     *       "logs": [
     *           {
     *               "startMilliseconds": 9100,
     *               "endMilliseconds": 15660,
     *               "speaker": "Suzie Smith",
     *               "description": ">> Speaker 1: So, tell me a little bit about your hometown. Tell me about Sun Valley, living there and having kind so of [inaudible]. >> Rebecca Rush: I"
     *           },
     *           {
     *               "startMilliseconds": 15660,
     *               "endMilliseconds": 22480,
     *               "speaker": "",
     *               "description": "live in paradise. I live in Sun Valley Idaho and I live there for the mountain bike trails specifically. A lot of people know Sun Valley for"
     *           }
     *       ],
     *       "formats": {
     *           "Timecoded JSON": "https://s3.amazonaws.com/transcripts.mediasilo.com/709352581VSEJ/3473618.json"
     *       }
     *  }
     ******************************************************************************************/

    /**
     * Gets the transcript for the given asset
     * @param $assetId
     * @return Transcript
     */
    public function getTranscript($assetId)
    {
        return $this->transcriptProxy->getTranscript($assetId);
    }










    /******************************************************************************************
     * QuickLinks
     *
     * QuickLinks are one of MediaSilo's primary mechanisms for sharing assets with a designated
     * audience through a specialized client application. QuickLinks contain one or more Assets
     * which can be shared publicly (with or without a password) and privately (with other
     * MediaSilo users on your account). QuickLinks contain a configuration which encapsulates a
     * list of Settings relevant to the QuickLink. Additionally, you can send a QuickLink to a
     * specified audience through our Shares endpoint.
     *
     * Example QuickLinks Object
     *
     *  {
            "id": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
            "legacyUuid": null,
            "title": "Comments Pinned",
            "description": "",
            "assetIds": [
                "1234ABCD-12AB-1234-1234ABCD1234ABCD",
                "2234ABCD-12AB-1234-1234ABCD1234ABCD",
                "3234ABCD-12AB-1234-1234ABCD1234ABCD",
                "4234ABCD-12AB-1234-1234ABCD1234ABCD",
                "5234ABCD-12AB-1234-1234ABCD1234ABCD"
            ],
            "configuration": {
                "id": "",
                "settings": [
                    {
                        "key": "notify_email",
                        "value": "false"
                    },
                    {
                        "key": "show_metadata",
                        "value": null
                    },
                    {
                        "key": "allow_download",
                        "value": "false"
                    },
                    {
                        "key": "audience",
                        "value": "public"
                    },

                ]
            },
            "shares": [
                {
                    "id": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
                    "targetObjectId": "2234ABCD-12AB-1234-1234ABCD1234ABCD",
                    "emailShare": {
                        "audience": [
                            {
                                "firstName": Mike,
                                "lastName": Moo,
                                "email": "mikemoo@mediasilo.com",
                                "userId": 1234ABCD-12AB-1234-1234ABCD1234ABCD
                            }

                    }
                }
            ],
            "ownerId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
            "accountId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
            "created": 1399905388335,
            "modified": 1399905414758,
            "expires": 1407681414757,
            "url": "https://qlnk.io/ql/1234ABCD-12AB-1234-1234ABCD1234ABCD",
            "private": false
        }
     ******************************************************************************************/

    /**
     * Persists a QuickLink in MediaSilo
     *
     * NOTE: This does not send it, only creates it.
     *
     * @param string $title Title for the Quicklink
     * @param string $description Description for the Quicklink
     * @param array $assetIds Array of Asset ID to be included in quicklink
     * @param array $settings Key/Value associative array of settings
     * @return Quicklink Hydrated model of created quicklink
     */
    public function createQuickLink($title, $description = "", array $assetIds = array(), array $settings = array())
    {
        $newSettings = array();
        foreach ($settings as $key => $value) {
            array_push($newSettings, new Setting((string)$key, (string)$value));
        }
        $configuration = new Configuration(null, $newSettings);

        $quickLink = new QuickLink($assetIds, $configuration, $description, array(), $title);
        $this->quicklinkProxy->createQuickLink($quickLink);
        return $quickLink;
    }

    /**
     * Fetches a quicklink based on UUID
     *
     * @param String $id
     * @param Bool - true to embed analytics
     * @returns Quicklink
     */
    public function getQuickLink($id, $includeAnalytics = false)
    {
        return $this->quicklinkProxy->getQuickLink($id, $includeAnalytics);
    }

    /**
     * Fetches a list of Quicklinks
     *
     * @param String - query params to be included with the request
     * @param Bool - true to embed analytics
     * @returns Quicklink[] Array of Quicklink Objects
     */
    public function getQuickLinks($params = null, $includeAnalytics = false)
    {
        return $this->quicklinkProxy->getQuicklinks($params, $includeAnalytics);
    }

    /**
     * Fetches a list of QuickLinks wrapped in a pagination object
     *
     * @param $params
     * @param bool $includeAnalytics
     * @return mixed
     */
    public function getQuicklinksPaginated($params, $includeAnalytics = false) {
        if (!is_null($params)) {
            return $this->quicklinkProxy->getQuicklinks($params, $includeAnalytics, true);
        } else {
            return $this->quicklinkProxy->getQuicklinks(null, $includeAnalytics, true);
        }
    }

    /**
     * Updates a QuickLink in MediaSilo
     *
     * @param String $id UUID of quicklink to update
     * @param String $title Title for the Quicklink
     * @param String $description Description for the Quicklink
     * @param Array $assetIds Array of Asset ID to be included in quicklink
     * @param Array $settings Key/Value associative array of settings
     * @param String $expires timestamp
     * @return Void
     */
    public function updateQuickLink($id, $title = null, $description = null, array $assetIds = null, array $settings = null, $expires = null)
    {
        $assets = null;
        $configuration = null;

        if (is_array($settings)) {
            $newSettings = array();
            foreach ($settings as $key => $value) {
                array_push($newSettings, new Setting((string)$key, (string)$value));
            }
            $configuration = new Configuration(null, $newSettings);
        } else {
            $configuration = new Configuration(null, null);
        }

        if (is_array($assetIds)) {
            $assets = $assetIds;
        }

        $quickLink = new QuickLink($assets, $configuration, $description, array(), $title);
        $quickLink->expires = $expires;
        $quickLink->setId($id);
        $this->quicklinkProxy->updateQuicklink($quickLink);
    }










    /******************************************************************************************
     * Quick Link Settings
     *
     * http://developers.mediasilo.com/quicklinksettings
     *
     * QuickLinkSettings are used to store user-defined presets that can be applied to individual QuickLinks.
     *
     * Example Settings Object
     *
     *  {
     *      "name": "My New Quick Link",
     *      "description": "Some great new shots from the red carpet",
     *      "targetObjectId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *      "keyValuePairs": [
     *          {
     *              "key": "audience",
     *              "value": "public"
     *          },,
     *          {
     *              "key": "expiration_value",
     *              "value": 365
     *          }
     *      ]
     *  }
     ******************************************************************************************/

    /**
     * Get an individual Quicklink Preset by UUID
     * @param String $settingId
     * @return Object
     */
    public function getQuickLinkSetting($settingId)
    {
        $clientResponse = $this->webClient->get(MediaSiloResourcePaths::QUICK_LINK_SETTINGS . "/" . $settingId);
        return json_decode($clientResponse->getBody());
    }

    /**
     * Get a list of Quicklink Presets
     * @return Array[Object]
     */
    public function getQuickLinkSettings()
    {
        $clientResponse = $this->webClient->get(MediaSiloResourcePaths::QUICK_LINK_SETTINGS);
        return json_decode($clientResponse->getBody());
    }










    /******************************************************************************************
     * Comments
     *
     * http://developers.mediasilo.com/comments
     *
     * Comments provide threaded feedback and discussion capability, optionally at specific
     * timecodes within an Asset. Comments on QuickLink Assets are specific to that QuickLink,
     * and are separate from comments on the same Asset in other QuickLinks or on the Asset
     * itself outside of any QuickLink.
     *
     * Example Comment Object
     *
     *  {
     *       "context":"1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *       "at":"1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *       "body":"Hey Yogi, the Ranger's not going to like this part.",
     *       "startTimeCode":121,
     *       "endTimeCode":134,
     *       "user":{
     *           "id":"1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *           "userName":booboo,
     *           "firstName":"Boo-Boo",
     *           "lastName":"Bear",
     *           "email":"booboo@example.com"
     *       }
     *  }
     ******************************************************************************************/

    /**
     * Creates a Comment on an Asset in a Quicklink
     *
     * @param String $quicklinkId
     * @param String $assetId
     * @param String $commentBody
     * @param String $inResponseTo
     * @param String $startTimeCode
     * @param String $endTimeCode
     * @param String $user
     * @return String
     */
    public function commentOnQuickLinkAsset($quicklinkId, $assetId, $commentBody, $inResponseTo = null, $startTimeCode = null, $endTimeCode = null, $user = null) {
        $comment = new Comment($assetId, $inResponseTo, $quicklinkId, $commentBody);
        $comment->startTimeCode = $startTimeCode;
        $comment->endTimeCode = $endTimeCode;
        $comment->user = $user;

        $resourcePath = sprintf(MediaSiloResourcePaths::QUICK_LINK_COMMENTS, $quicklinkId, $assetId);
        $result = json_decode($this->webClient->post($resourcePath, $comment->toJson()));
        return $result->id;
    }

    /**
     * Get a list of comments on an Asset in a Quicklink
     *
     * @param String $quickLinkId
     * @param String $assetId
     * @return Array[Object]
     */
    public function getQuickLinkComments($quickLinkId, $assetId)
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::QUICK_LINK_COMMENTS, $quickLinkId, $assetId);

        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }

    /**
     * Get a list of comments on an Asset in a Quicklink in a specified format
     *
     * @param String $quickLinkId
     * @param String $assetId
     * @param String $format
     * @return Array[Object]
     */
    public function getQuickLinkCommentsAs($quickLinkId, $assetId, $format="txt")
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::QUICKLINK_COMMENTS_EXPORT, $quickLinkId, $assetId, $format);
        $clientResponse = $this->webClient->get($resourcePath);
        return $clientResponse->getBody();
    }










    /******************************************************************************************
     * Share
     *
     * http://developers.mediasilo.com/shares
     *
     * Shares allows you to send a quick link to a given audience of internal
     * or external users.
     *
     * Example Share Object
     *
     *  {
     *       "targetObjectId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
     *       "emailShare": {
     *       "audience": [
     *           {
     *           "email": "deathstar@mediasilo.com",
     *           "firstName": "Lord",
     *           "lastName": "Vador",
     *           "userId": null
     *           }
     *       ],
     *       "message": "We have gathered intel on the rebels activity.",
     *       "subject": "The rebels are on Endor!"
     *       }
     *  }
     ******************************************************************************************/

    /**
     * Shares a QuickLink
     *
     * @param string $quicklinkId ID of the quicklink you want to share
     * @param string $subject Subject for the email
     * @param string $message Body for the email
     * @param array $emailAddresses Email addresses (strings) of email recipients
     * @return Share object
     */
    public function shareQuickLink($quicklinkId, $subject = "", $message = "", array $emailAddresses = array()) {
        $emailRecipients = array();
        foreach($emailAddresses as $email) {
            array_push($emailRecipients, new EmailRecipient($email, null));
        }
        $emailShare = new EmailShare($emailRecipients, $message, $subject);
        $share = new Share($emailShare, null, $quicklinkId);
        $this->shareProxy->createShare($share);
        return $share;
    }

    /**
    * For a quick link that has already been shared, this will return those shares 
    * allowing you to see when and to who the quicklink was shared
    *
    * @param string $quicklinkId The quicklink for which you'd like to retrieve shares
    */
    public function getQuicklinkShares($quicklinkId) {
        return $this->shareProxy->getShares($quicklinkId);
    }

    /**
     * Gets events on a quicklink
     * @param String $quicklinkIds
     * @return Array
     */
    public function getQuicklinkAggregateEvents($quicklinkIds) {
        $quickLinkEvents = $this->quicklinkAnalyticsProxy->getQuicklinkAggregateEvents($quicklinkIds);
        return $quickLinkEvents;
    }










    /******************************************************************************************
     * Users
     *
     * http://developers.mediasilo.com/users
     *
     * Example User Object
     *
     *  {
            "id": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
            "numericId": 12345,
            "defaultRoleTemplateId": 1234ABCD-12AB-1234-1234ABCD1234ABCD,
            "userName": "winnie",
            "accountId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
            "firstName": "Pooh",
            "lastName": "Bear",
            "company": "MediaSilo",
            "email": "dev@mediasilo.com",
            "phone": "0",
            "mobile": "0",
            "address": {
                "address1": "123 100 Acre Wood Street",
                "address2": "",
                "city": "",
                "province": "Wales",
                "postalCode": "XL7-123",
                "country": "GB"
            },
            "status": "ACTIVE",
            "sso": false,
            "ssoId": null,
            "roles": [],
            "tags": [
                "poohbear",
                "piglet"
            ],
            "preferences": [
                {
                    "id": 439876,
                    "userId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
                    "name": "proxy_usesocks",
                    "value": "false"
                },
                {
                    "id": 439877,
                    "userId": "1234ABCD-12AB-1234-1234ABCD1234ABCD",
                    "name": "proxy_useftp",
                    "value": "false"
                }
            ]
        }
     ******************************************************************************************/

    /**
     * Get the current user
     *
     * @return mixed
     */
    public function me()
    {
        $clientResponse = $this->webClient->get(MediaSiloResourcePaths::ME);
        $this->me = json_decode($clientResponse->getBody());

        return $this->me;
    }

    /**
     * Gets a User by UUID
     *
     * @param String $userId
     * @return Array
     */
    public function getUser($userId)
    {
        return $this->userProxy->getUser($userId);
    }

    /**
     * Get a list of a Projects's users
     * @param String $projectId
     * @return Array[Object]
     */
    public function getProjectUsers($projectId)
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::PROJECT_USERS, $projectId);
        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }

    /**
     * Persists Updates to a User Object
     *
     * @param User $user
     */
    public function updateUser(User $user) {
        $this->userProxy->updateUser($user);
    }

    /**
     * Updates a User profile based on update-able parameters
     *
     * @param $userId
     * @param null $firstName
     * @param null $lastName
     * @param null $username
     * @param null $email
     * @param null $password
     * @param null $address
     * @param null $phone
     * @param null $mobile
     * @param null $company
     * @param null $status
     * @param null $defaultRowTemplateId
     */
    public function updateUserProfile($userId, $firstName = null, $lastName = null, $username = null, $email = null, $password = null,
                               $address = null, $phone = null, $mobile = null, $company = null, $status = null, $defaultRowTemplateId = null)
    {
        $user = new User($address,$company,$defaultRowTemplateId, $email,$firstName, $userId, $lastName, $mobile, null, $phone, null, null, null, $status, $username, null);

        if (!is_null($password)) {
            $user->setPassword($password);
        }

        $this->userProxy->updateUser($user);
    }










    /******************************************************************************************
     * Saved Search
     *
     * http://developers.mediasilo.com/savedsearch
     *
     * Saved Search is a specialized object that stores the criteria needed for searching for
     * specific assets, these objects can be saved and retireved via the API. In the MediaSilo
     * application you will see these SavedSearch objects which will allow the MediaSilo application
     * to repeat the search with the given criteria with a single click.
     *
     * Example Saved Search Object
     *
     *   {
     *       "name": "Assets With Comments",
     *       "description": "My saved search",
     *       "keyValuePairs":
     *       [
     *           {
     *               "key": "projectid",
     *               "value": "1111",
     *               "operator": "is"
     *           },
     *           {
     *               "key": "filetypefilter",
     *               "value": "video,",
     *               "operator": "is"
     *           },
     *           {
     *               "key": "hascomments",
     *               "value": "True",
     *               "operator": "is"
     *           }
     *       ]
     *   }
     ******************************************************************************************/

    /**
     * Get a specific saved search
     *
     * @param String $id
     * @return Array
     */
    public function getSavedSearch($id)
    {
        $clientResponse = $this->webClient->get(MediaSiloResourcePaths::SAVED_SEARCHES . "/" . $id);
        return json_decode($clientResponse->getBody());
    }

    /**
     * Gets a list of  Saved Searches
     * @return Array
     */
    public function getSavedSearches()
    {
        $clientResponse = $this->webClient->get(MediaSiloResourcePaths::SAVED_SEARCHES);
        return json_decode($clientResponse->getBody());
    }










    /******************************************************************************************
     * Tags
     *
     * http://developers.mediasilo.com/tags
     *
     * Tags are basic Strings that can be attached to a given object which can denote content or
     * purpose of the given object. Tags can also be used to filter objects such as users.
     * Currently tags are only available for users.
     *
     * Example Tag Object
     *
     *   {
            "id": "8C0EB657-1111-1111-56610D0B33185B74",
            "tags":
            [
                "Boston",
                "Tree"
            ]
        }
     ******************************************************************************************/

    /**
     * Gets a list of my tags
     * @return Array[Object]
     */
    public function getMyTags()
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::USER_TAGS, $this->me->id);
        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }

    /**
     * Gets a list of User Tags by User UUID
     * @param String $userId
     * @return Array[Object]
     */
    public function getUsersTags($userId)
    {
        $resourcePath = sprintf(MediaSiloResourcePaths::USER_TAGS, $userId);
        $clientResponse = $this->webClient->get($resourcePath);
        return json_decode($clientResponse->getBody());
    }










    /******************************************************************************************
     * Distribution Lists
     *
     * http://developers.mediasilo.com/distribution-lists
     *
     * Distribution Lists enable users to store frequently-used groups of email recipients as
     * presets, simplifying the workflow of distributing Assets to production teams or decision
     * makers via QuickLinks.
     *
     * Example Distribution List Object
     *
     *   {
     *       "name": "Puppets",
     *       "description": "A group of opinionated puppets.",
     *       "recipients": [
     *           {
     *               "firstName": "Big",
     *               "lastName": "Bird",
     *               "email": "thebigbyrd@sesamestreet.org",
     *               "userId": "bf33b7fc-53f1-4517-bd36-afe5ce45add5"
     *           },
     *           {
     *               "firstName": "Oscar",
     *               "lastName": "Grouch",
     *               "email": "leavemealone@sesamestreet.org",
     *               "userId": "c84560b3-cccd-4da5-a9e4-2fd98937f681"
     *           },
     *           {
     *               "email": "mspiggy@themuppetshow.org"
     *           }
     *       ]
     *   }
     ******************************************************************************************/

    /**
     * Gets a list of Distribution Lists
     * @return Array[Object]
     */
    public function getDistributionLists()
    {
        $clientResponse = $this->webClient->get(MediaSiloResourcePaths::DISTRIBUTION_LISTS);
        return json_decode($clientResponse->getBody());
    }

    /**
     * Gets a Distribution List by UUID
     * @param String $id
     * @return Object
     */
    public function getDistributionList($id)
    {
        $clientResponse = $this->webClient->get(MediaSiloResourcePaths::DISTRIBUTION_LISTS . "/" . $id);
        return json_decode($clientResponse->getBody());
    }
}
