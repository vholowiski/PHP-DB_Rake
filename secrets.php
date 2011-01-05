<?PHP 

define('DEVELOPMENT_ENV', true); #change to false if this is production.  This lets you have different username/password/db for test and production

if (DEVELOPMENT_DB) {
	define ('MYSQL_USERNAME', "username");
	define ('MYSQL_PASSWORD', "password");
	define ('MYSQL_DATABASE', "development_database");
} else {
	define ('MYSQL_USERNAME', "username");
	define ('MYSQL_PASSWORD', "password");
	define ('MYSQL_DATABASE', "production_database");
}
?>