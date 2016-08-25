<?php

namespace OAuth\OAuth2\Service;

use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\OAuth2\Service\Exception\InvalidAccessTypeException;
use OAuth\Common\Http\Uri\Uri;

class Google extends AbstractService
{
    /**
     * Defined scopes - More scopes are listed here:
     * https://developers.google.com/oauthplayground/
     *
     * Make a pull request if you need more scopes.
     */

    // Basic
    const SCOPE_EMAIL                       = 'email';
    const SCOPE_PROFILE                     = 'profile';

    const SCOPE_USERINFO_EMAIL              = 'https://www.googleapis.com/auth/userinfo.email';
    const SCOPE_USERINFO_PROFILE            = 'https://www.googleapis.com/auth/userinfo.profile';

    // Google+
    const SCOPE_GPLUS_ME                    = 'https://www.googleapis.com/auth/plus.me';
    const SCOPE_GPLUS_LOGIN                 = 'https://www.googleapis.com/auth/plus.login';

    // Google Drive
    const SCOPE_DOCUMENTSLIST               = 'https://docs.google.com/feeds/';
    const SCOPE_SPREADSHEETS                = 'https://spreadsheets.google.com/feeds/';
    const SCOPE_GOOGLEDRIVE                 = 'https://www.googleapis.com/auth/drive';
    const SCOPE_DRIVE_APPS                  = 'https://www.googleapis.com/auth/drive.appdata';
    const SCOPE_DRIVE_APPS_READ_ONLY        = 'https://www.googleapis.com/auth/drive.apps.readonly';
    const SCOPE_GOOGLEDRIVE_FILES           = 'https://www.googleapis.com/auth/drive.file';
    const SCOPE_DRIVE_METADATA_READ_ONLY    = 'https://www.googleapis.com/auth/drive.metadata.readonly';
    const SCOPE_DRIVE_READ_ONLY             = 'https://www.googleapis.com/auth/drive.readonly';
    const SCOPE_DRIVE_SCRIPTS               = 'https://www.googleapis.com/auth/drive.scripts';

    // Adwords
    const SCOPE_ADSENSE                     = 'https://www.googleapis.com/auth/adsense';
    const SCOPE_ADWORDS                     = 'https://adwords.google.com/api/adwords/';
    const SCOPE_GAN                         = 'https://www.googleapis.com/auth/gan'; // google affiliate network...?

    // Google Analytics
    const SCOPE_ANALYTICS                   = 'https://www.googleapis.com/auth/analytics';
    const SCOPE_ANALYTICS_EDIT              = 'https://www.googleapis.com/auth/analytics.edit';
    const SCOPE_ANALYTICS_MANAGE_USERS      = 'https://www.googleapis.com/auth/analytics.manage.users';
    const SCOPE_ANALYTICS_READ_ONLY         = 'https://www.googleapis.com/auth/analytics.readonly';

    // Other services
    const SCOPE_BOOKS                       = 'https://www.googleapis.com/auth/books';
    const SCOPE_BLOGGER                     = 'https://www.googleapis.com/auth/blogger';
    const SCOPE_CALENDAR                    = 'https://www.googleapis.com/auth/calendar';
    const SCOPE_CALENDAR_READ_ONLY          = 'https://www.googleapis.com/auth/calendar.readonly';
    const SCOPE_CONTACT                     = 'https://www.google.com/m8/feeds/';
    const SCOPE_CHROMEWEBSTORE              = 'https://www.googleapis.com/auth/chromewebstore.readonly';
    const SCOPE_GMAIL                       = 'https://mail.google.com/mail/feed/atom';
    const SCOPE_PICASAWEB                   = 'https://picasaweb.google.com/data/';
    const SCOPE_SITES                       = 'https://sites.google.com/feeds/';
    const SCOPE_URLSHORTENER                = 'https://www.googleapis.com/auth/urlshortener';
    const SCOPE_WEBMASTERTOOLS              = 'https://www.google.com/webmasters/tools/feeds/';
    const SCOPE_TASKS                       = 'https://www.googleapis.com/auth/tasks';

    // Cloud services
    const SCOPE_CLOUDSTORAGE                = 'https://www.googleapis.com/auth/devstorage.read_write';
    const SCOPE_CONTENTFORSHOPPING          = 'https://www.googleapis.com/auth/structuredcontent'; // what even is this
    const SCOPE_USER_PROVISIONING           = 'https://apps-apis.google.com/a/feeds/user/';
    const SCOPE_GROUPS_PROVISIONING         = 'https://apps-apis.google.com/a/feeds/groups/';
    const SCOPE_NICKNAME_PROVISIONING       = 'https://apps-apis.google.com/a/feeds/alias/';

    // Old
    const SCOPE_ORKUT                       = 'https://www.googleapis.com/auth/orkut';
    const SCOPE_GOOGLELATITUDE =
        'https://www.googleapis.com/auth/latitude.all.best https://www.googleapis.com/auth/latitude.all.city';
    const SCOPE_OPENID                      = 'openid';

    // YouTube
    const SCOPE_YOUTUBE_GDATA               = 'https://gdata.youtube.com';
    const SCOPE_YOUTUBE_ANALYTICS_MONETARY  = 'https://www.googleapis.com/auth/yt-analytics-monetary.readonly';
    const SCOPE_YOUTUBE_ANALYTICS           = 'https://www.googleapis.com/auth/yt-analytics.readonly';
    const SCOPE_YOUTUBE                     = 'https://www.googleapis.com/auth/youtube';
    const SCOPE_YOUTUBE_READ_ONLY           = 'https://www.googleapis.com/auth/youtube.readonly';
    const SCOPE_YOUTUBE_UPLOAD              = 'https://www.googleapis.com/auth/youtube.upload';
    const SCOPE_YOUTUBE_PATNER              = 'https://www.googleapis.com/auth/youtubepartner';
    const SCOPE_YOUTUBE_PARTNER_EDIT        = 'https://www.googleapis.com/auth/youtubepartner-channel-edit';

    // Google Glass
    const SCOPE_GLASS_TIMELINE              = 'https://www.googleapis.com/auth/glass.timeline';
    const SCOPE_GLASS_LOCATION              = 'https://www.googleapis.com/auth/glass.location';

    // Android Publisher
    const SCOPE_ANDROID_PUBLISHER           = 'https://www.googleapis.com/auth/androidpublisher';

    protected $accessType = 'online';


    public function setAccessType($accessType)
    {
        if (!in_array($accessType, array('online', 'offline'), true)) {
            throw new InvalidAccessTypeException('Invalid accessType, expected either online or offline');
        }
        $this->accessType = $accessType;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizationEndpoint()
    {
        return new Uri('https://accounts.google.com/o/oauth2/auth?access_type=' . $this->accessType);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenEndpoint()
    {
        return new Uri('https://accounts.google.com/o/oauth2/token');
    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessTokenResponse($responseBody)
    {
        $data = json_decode($responseBody, true);

        if (null === $data || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
        }

        $token = new StdOAuth2Token();
        $token->setAccessToken($data['access_token']);
        $token->setLifetime($data['expires_in']);

        if (isset($data['refresh_token'])) {
            $token->setRefreshToken($data['refresh_token']);
            unset($data['refresh_token']);
        }

        unset($data['access_token']);
        unset($data['expires_in']);

        $token->setExtraParams($data);

        return $token;
    }
}
