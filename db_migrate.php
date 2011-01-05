<?php
include("secrets.php");

#this file creates the database, and runs migrations for me. It's all automatic
#you really shouldn't leave this on a production server
#currently only works if MySQL DB is on localhost

define ('ROOT_MYSQL_USERNAME', "root"); #need these to create the database and create the user
define ('ROOT_MYSQL_PASSWORD', "password"); #this should be your root password, or a user that has permission to create databases and assign permissions

echo("Attempting to connect to the MySQL Server... ");
$link=mysql_connect('localhost', ROOT_MYSQL_USERNAME, ROOT_MYSQL_PASSWORD);
if ($link) {
	echo("Ok\n");
	#first, create the database. Should be defined in secrets already.
	$query="CREATE DATABASE IF NOT EXISTS ".MYSQL_DATABASE;
	echo("Creating Database if it doesn't exist...");
	$result=mysql_query($query);
	if ($result) {
		echo("Ok\n");
		#now try selecting the database
		echo("Now trying to select ".MYSQL_DATABASE."...");
		$db_selected=mysql_select_db(MYSQL_DATABASE, $link);
		if ($db_selected) {
			echo("Ok\n");
			
			#check if requested user exists. Assuming MYSQL_USER and MYSQL_PASSWORD are defined in functions.php or secret.php
			check_and_create_user(MYSQL_USERNAME, MYSQL_PASSWORD);
			#if not, create
			#and give permission to the db
			if (!check_if_has_permission(MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DATABASE)) { 
				if (give_user_permission(MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DATABASE)) { #gives full permission to this db. you'll need to change both functions if you want something else
				} else {echo(MYSQL_USERNAME." did not get permissions!\n");}
			} else {
				echo(MYSQL_USERNAME." already had permission on ".MYSQL_DATABASE." so we didn't change anything\n");
			}
			
			#*******This is where the tables get created.
			#make copies of this section - one copy for each table	
			$table="table name";
			$query="CREATE TABLE $table (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, blah blah blah, replace with your columns)";
			create_table_if_not_exists($table, $query);
			#******END Table creation. Repeat as needed
			
		} else {
			echo("Failed\n");
			echo("Failed to select ".MYSQL_DATABASE.", although it seems to exist. Something went horribly wrong!");
		}
	} else {
		echo("Failed\n");
	}
	
} else {
	echo("Failed\n");
	echo("Failed to connect to the MySQL Server on localhost\n");
}

function give_user_permission($username, $password, $database) {
	#if we're here, we can assume we are connected as root
	
	echo("Reselecting ".MYSQL_DATABASE." for fun...");
	$db_selected=mysql_select_db(MYSQL_DATABASE);
	if ($db_selected){echo("ok\n"); }else{echo("Failed\n");}
	
	echo("Granting $username permission to $database...");
	
	$query="GRANT ALL on * to '$username'@'localhost' IDENTIFIED BY '$password'";
	$result=mysql_query($query);
	if (!$result) {
		echo("Failed to grant privileges. This is bad - DIE");
		exit();
	}
	$query2="FLUSH PRIVILEGES"; #for good measure
	$result2=mysql_query($query);
	if ($query) {
		echo("Ok\n");
		echo("Switching to $username...");
		mysql_close();
		$link=mysql_connect('localhost', $username, $password);
		if ($link) {
			echo("Ok\n");
			#now see if i can describe database. if i can't, then i didn't get permission
			$db_selected=mysql_select_db(MYSQL_DATABASE);
			$query3="show tables";
			$result3=mysql_query($query3);
			if ($result3) {
				echo("Verified $username has permissions to $database\n");
				return true;
			} else {
				echo("Somehow, $username didn't get permission to $database\n");
				echo("Continuing as ".ROOT_MYSQL_USERNAME."\n");
				$link=mysql_connect('localhost', ROOT_MYSQL_USERNAME, ROOT_MYSQL_PASSWORD);
				if (!$link) {echo("Oh Snap, couldn't even connect as ".ROOT_MYSQL_USERNAME." - DIE"); exit();} #this really shouldn't happen, unless something horrible happened
			}
		} else {
			echo("Failed - this shouldn't happen!\n");
			$link=mysql_connect('localhost', ROOT_MYSQL_USERNAME, ROOT_MYSQL_USERNAME);
			if (!$link) {echo("Oh Snap, couldn't even connect as ".ROOT_MYSQL_USERNAME." - DIE"); exit();} #this really shouldn't happen, unless something horrible happened
		}
		
	} else {
		echo("Failed\n");
		echo("Failed to grant user permissions. Likely something wrong with your SQL: $query");
		echo("Continuing as ".ROOT_MYSQL_USER);
		return false;
	}
}

function check_if_has_permission($username, $password, $database) {
	echo("Checking if $username has permissions on $database...");
	#shut down the connection as root
	mysql_close();
	#attempt to connect as MYSQL_USERNAME
	#echo("Attempting to connect as $username, $password\n");
	$link=mysql_connect('localhost', $username, $password);
	if (!$link) {
		echo("Failed to connect to $database as $username!\n"); #this shouldn't actually happen but who knows...
		echo("User was not given as permission, continuing as ".ROOT_MYSQL_USERNAME."\n");
		$link=mysql_connect('localhost', ROOT_MYSQL_USERNAME, ROOT_MYSQL_PASSWORD);
		if (!$link) {echo("Oh Snap, couldn't even connect as ".ROOT_MYSQL_USERNAME." - DIE"); exit();} #this really shouldn't happen, unless something horrible happened
		return false;
	} else {
		#now select the database as the user
		$db_selected=mysql_select_db(MYSQL_DATABASE, $link);
		#and do something that will error out if we dont have permission. How about describe $database?
		$query="DESCRIBE $database";
		$result=mysql_query($query);
		if ($result) {
			echo("Already has access\n");
			return true;
		} else {
			echo("No\n");
			#switch back to root user
			mysql_close();
			$link=mysql_connect('localhost', ROOT_MYSQL_USERNAME, ROOT_MYSQL_PASSWORD);
			if (!$link) {echo("Oh Snap, couldn't even connect as ".ROOT_MYSQL_USERNAME." - DIE"); exit();} #this really shouldn't happen, unless something horrible happened
			return false;
		}
	}
}

function table_exists($table) {
	echo("Checking if table $table exists...");
	$query = "SELECT * from $table";
	$result=mysql_query($query);
	if ($result) {
		echo("Yes\n");
		return true;
	} else {
		echo("No\n");
		return false;
	}
}

function create_table($query, $table) {
	echo("Creating table $table...");
	$result=mysql_query($query);
	if ($result) {
		echo("Ok\n");
		return true;
	} else {
		echo("Failed.\n");
		return false;
	}
}

function create_table_if_not_exists($table, $query) {
	if (table_exists($table)) {
		echo("Skipped, it already exists\n");
		return false;
	} else {
		if (create_table($query, $table)) {
			return true;
		} else {
			echo("$table creation failed, something is probably wrong with your SQL: $query\n");
			return false;
		}
	}
	#on to the next table
}

function check_and_create_user($username, $password) {
	$query="SELECT user FROM mysql.user WHERE user='$username'";
	$result=mysql_query($query);
	echo("Checking if User $username exists...");
	if (mysql_num_rows($result) > 0) {
		echo("Yes, skipping creation\n");
		echo("Note - If the user already exists, we didn't touch the password. The password might not be what you expect it to be!\n");
	} else {
		echo("No\n");
		echo("Creating user...");
		#first flush privliges. Don't care if it works or not. 
		#this is in case you are creating a user who was just deleted
		$query="FLUSH PRIVILEGES";
		$result=mysql_query($query);
		$query="CREATE USER '$username'@'localhost' IDENTIFIED BY '$password'";
		$result=mysql_query($query);
		$flush_query="FLUSH PRIVILEGES";
		$flush_result=mysql_query($flush_query);
		if ($result) {
			echo("Ok\n");
			return true;
		} else {
			echo("Failed\n");
			echo("User creation failed, check your SQL: $query\n");
			return false;
		}
	}
}

mysql_close();
?>