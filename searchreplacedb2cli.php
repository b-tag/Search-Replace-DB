#!/usr/bin/php -q

<?php

/**
 * To run this script, execute something like this:
 * `./searchreplacedb2cli.php -h localhost -u root -d test -c utf8 -s "findMe" -r "replaceMe"`
 * use the --dry-run flag to do a dry run without searching/replacing.
 * this script currently affects all tables in a db there are @TODOs below...
 */

require_once('searchreplacedb2.php'); // include the proper srdb script

echo "########################### Ignore Above ###############################\n\n";

// source: https://github.com/interconnectit/Search-Replace-DB/blob/master/searchreplacedb2.php

/* Flags for options, all values required */
$shortopts  = "";
$shortopts .= "h:";  // host name // $host
$shortopts .= "d:"; // database name // $data
$shortopts .= "u:"; // user name // $user
$shortopts .= "p:"; // password // $pass
$shortopts .= "t:"; // table prefix // $tprfx
$shortopts .= "c:"; // character set // $char
$shortopts .= "s:"; // search // $srch
$shortopts .= "r:"; // replace // $rplc
$shortopts .= ""; // These options do not accept values

// All long options require values
$longopts  = array(
    "host:",    // $host
    "database:", // $data
    "user:", // $user
    "pass:", // $pass
	"table-prefix:", // $tprfx
    "charset:", // $char
    "search:", // $srch
    "replace:", // $rplc
    "help", // $help_text
    //@TODO write dry-run to also do a search without a replace.
    "dry-run", // engage in a dry run, print options, show results
);

/* Store arg values */
$arg_count = $_SERVER["argc"];
$args_array = $_SERVER["argv"];
$options = getopt($shortopts, $longopts); // Store array of options and values
// var_dump($options); // return all the values

/* Map options to correct vars from srdb script */
if (isset($options["h"])){
  $host = $options["h"];}
elseif(isset($options["host"])){
  $host = $options["host"];}
else{
  echo "Abort! Host name required, use --host or -h\n";
  exit;}

if (isset($options["d"])){
  $data = $options["d"];}
elseif(isset($options["database"])){
  $data = $options["database"];}
else{
  echo "Abort! Database name required, use --database or -d\n";
  exit;}

if (isset($options["u"])){
  $user = $options["u"];}
elseif(isset($options["user"])){
  $user = $options["user"];}
  else{
  	echo "Abort! Host name required, use --host or -h\n";
  	exit;}
  	
if (isset($options["p"])){
  $pass = $options["p"];}
elseif(isset($options["pass"])){
  $pass = $options["pass"];}
  else{
  	echo "Abort! Password is required, use --pass or -p\n";
  	exit;}
  	
if (isset($options["t"])){
	$tprfx = $options["t"];}
elseif (isset($options["table-prefix"])){
  	$tprfx = $options["table-prefix"];} 

if (isset($options["c"])){
  $char = $options["c"];}
elseif(isset($options["charset"])){
  $char = $options["charset"];}

if (isset($options["s"])){
  $srch = $options["s"];}
elseif(isset($options["search"])){
  $srch = $options["search"];}
  else{
  	echo "Abort! Search string is required, use --search or -s\n";
  	exit;}
  	
if (isset($options["r"])){
  $rplc = $options["r"];}
elseif(isset($options["replace"])){
  $rplc = $options["replace"];}
  else{
  	echo "Abort! Replacement string is required, use --replace or -r\n";
  	exit;}
  	
/* Show values if this is a dry-run */
if (isset($options["dry-run"])){
echo "Are you sure these are correct?\n";
}
echo "host: ".$host."\n";
echo "database: ".$data."\n";
echo "user: ".$user."\n";
echo "pass: ".$pass."\n";
if( !empty($tprfx) ) echo "table-prefix: ".$tprfx."\n";
if( !empty($char) ) echo "charset: ".$char."\n";
echo "search: ".$srch."\n";
echo "replace: ".$rplc."\n\n";

/* Reproduce what's done in Case 3 to test the server before proceeding */
        $connection = @mysql_connect( $host, $user, $pass );
        if ( ! $connection ) {
                $errors[] = mysql_error( );
                echo "MySQL Connection Error: ";
                print_r($errors);
        }

        if ( ! empty( $char ) ) {
                if ( function_exists( 'mysql_set_charset' ) )
                        mysql_set_charset( $char, $connection );
                else
                        mysql_query( 'SET NAMES ' . $char, $connection );  // Shouldn't really use this, but there for backwards compatibility
        }

        // Do we have any tables and if so build the all tables array
        $all_tables = array( );
        @mysql_select_db( $data, $connection );
        
        $sql_query = "SHOW TABLES";
        if( !empty($tprfx)) // check if table prefix is set
        	$sql_query .= " LIKE '{$tprfx}%'";
        
        $all_tables_mysql = @mysql_query( $sql_query, $connection );

        if ( ! $all_tables_mysql ) {
                $errors[] = mysql_error( );
                echo "MySQL Table Error: ";
                print_r($errors);
        } else {
                while ( $table = mysql_fetch_array( $all_tables_mysql ) ) {
                        $all_tables[] = $table[ 0 ];
                }
                $tables_count = count($all_tables);
                printf("There " . ngettext("is " . $tables_count . " table", "are " . $tables_count . " tables", $tables_count) . " to be processed:\n\n");
                echo implode(', ', $all_tables);
        }

/**
 * @TODO allow selection of one or more tables. For now, use all.
 */
$tables = $all_tables;

/* Execute Case 5 with the actual search + replace */

if(!isset($options["dry-run"])){ // check if dry-run

echo "\n\nWorking... ";

if( !defined('STDIN') ) { // Only for NO CLI call, CLI set no timeout, no memory limit

    @ set_time_limit( 60 * 10 );
    // Try to push the allowed memory up, while we're at it
    @ ini_set( 'memory_limit', '1024M' );
    
}

// Process the tables
if ( isset( $connection ) )
$report = icit_srdb_replacer( $connection, $srch, $rplc, $tables );

// Output any errors encountered during the db work.
if ( ! empty( $report[ 'errors' ] ) && is_array( $report[ 'errors' ] ) ) {
echo "Find/Replace Errors: \n";
foreach( $report[ 'errors' ] as $error )
echo $error . '\n';
}

// Calc the time taken.
$time = array_sum( explode( ' ', $report[ 'end' ] ) ) - array_sum( explode( ' ', $report[ 'start' ] ) );

echo "Done. Report:\n\n";
printf( 'In the process of replacing "%s" with "%s", we scanned the %d ' . ngettext('table', 'tables', $report[ 'tables' ]) .
	' above, ' . ngettext('which has %d row', 'who have %d rows', $report[ 'rows' ]) . '. ',
	$srch, $rplc, $report[ 'tables' ], $report[ 'rows' ]); 
if ($report['change'] != 0){
	printf('Executed ' . ngettext('was %d db update', 'were %d db updates', $report[ 'updates' ]) . ', ' .
		'changing %d ' . ngettext('cell', 'cells', $report[ 'change' ]) . '. ',
		$report[ 'updates' ], $report[ 'change' ]);}
else { echo 'No changes to the db were made, because we found no cells containing the search string. ';}
echo 'Processing took ' . $time . ' seconds.';
}

?>
