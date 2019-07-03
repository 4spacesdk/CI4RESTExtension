<?php namespace RestExtension\Entities;

use OrmExtension\Extensions\Entity;

/**
 * Class OAuthClient
 * @package RestExtension\Entities
 * @property string $client_id
 * @property string $client_secret
 * @property string $redirect_uri
 * @property string $grant_types
 * @property string $scope
 * @property string $user_id
 * @property int $rate_limit # Hourly rate limit
 *
 * Many
 * @property ApiAccessLog $api_access_logs
 * @property ApiBlockedLog $api_blocked_logs
 * @property ApiErrorLog $api_error_logs
 * @property ApiUsageReport $api_usage_report
 */
class OAuthClient extends Entity {

}
