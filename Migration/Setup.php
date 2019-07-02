<?php namespace RestExtension\Migration;

use CodeIgniter\Config\Config;
use Config\RestExtension;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class Setup {

    public static function migrateUp() {
        Table::init('oauth_clients')->softDelete();

        /** @var RestExtension $config */
        $config = Config::get('RestExtension');
        if($config) {

            Table::init('oauth_clients')->column('rate_limits', ColumnTypes::INT, $config->defaultRateLimit);

            if($config->enableApiRouting)
                Table::init('api_routes')
                    ->create()
                    ->column('method', ColumnTypes::VARCHAR_27)
                    ->column('from', ColumnTypes::VARCHAR_511)
                    ->column('to', ColumnTypes::VARCHAR_511)
                    ->column('cacheable', ColumnTypes::BOOL_0)
                    ->column('version', ColumnTypes::VARCHAR_63)
                    ->column('scope', ColumnTypes::VARCHAR_2047)
                    ->column('is_public', ColumnTypes::BOOL_0);

            if($config->enableAccessLog)
                Table::init('api_access_logs')
                    ->create()
                    ->column('client_id', ColumnTypes::VARCHAR_127)
                    ->column('access_token', ColumnTypes::VARCHAR_63)
                    ->column('api_route_id', ColumnTypes::INT)
                    ->column('uri', ColumnTypes::VARCHAR_1023)
                    ->column('date', ColumnTypes::DATETIME)
                    ->column('milliseconds', ColumnTypes::INT)
                    ->column('ip_address', ColumnTypes::VARCHAR_27);

            if($config->enableBlockedLog)
                Table::init('api_blocked_logs')
                    ->create()
                    ->column('client_id', ColumnTypes::VARCHAR_127)
                    ->column('access_token', ColumnTypes::VARCHAR_63)
                    ->column('api_route_id', ColumnTypes::INT)
                    ->column('uri', ColumnTypes::VARCHAR_1023)
                    ->column('date', ColumnTypes::DATETIME)
                    ->column('reason', ColumnTypes::VARCHAR_255)
                    ->column('ip_address', ColumnTypes::VARCHAR_27);

            if($config->enableErrorLog)
                Table::init('api_error_logs')
                    ->create()
                    ->column('client_id', ColumnTypes::VARCHAR_127)
                    ->column('access_token', ColumnTypes::VARCHAR_63)
                    ->column('api_route_id', ColumnTypes::INT)
                    ->column('uri', ColumnTypes::VARCHAR_1023)
                    ->column('date', ColumnTypes::DATETIME)
                    ->column('code', ColumnTypes::INT)
                    ->column('message', ColumnTypes::VARCHAR_511)
                    ->column('ip_address', ColumnTypes::VARCHAR_27)
                    ->column('get', ColumnTypes::TEXT)
                    ->column('post', ColumnTypes::TEXT)
                    ->column('patch', ColumnTypes::TEXT)
                    ->column('put', ColumnTypes::TEXT)
                    ->column('headers', ColumnTypes::TEXT);

            if($config->enableUsageReporting)
                Table::init('api_usage_reports')
                    ->create()
                    ->column('client_id', ColumnTypes::VARCHAR_127)
                    ->column('date', ColumnTypes::DATETIME)
                    ->column('usage', ColumnTypes::INT);
        } else
            Table::init('oauth_clients')->column('rate_limits', ColumnTypes::INT);
    }

    public static function migrateDown() {
        Table::init('oauth_clients')
            ->dropColumn('rate_limits')
            ->dropColumn('deletion_id');

        Table::init('api_routes')->dropTable();
        Table::init('api_access_logs')->dropTable();
        Table::init('api_blocked_logs')->dropTable();
        Table::init('api_error_logs')->dropTable();
        Table::init('api_usage_reports')->dropTable();
    }

}
