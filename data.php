<?php
//set headers to NOT cache a page
header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
header("Pragma: no-cache"); //HTTP 1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

set_time_limit(30);

error_reporting(0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set("Europe/Helsinki");

$sql = new mysqli('***', '***', '***', '***'); // change these

$palveluhakemistolliset_all = $sql->query('SELECT DISTINCT dpjoomla_key FROM j17_dpjoomla');
$palveluhakemistolliset_all = $palveluhakemistolliset_all->fetch_all();
$palveluhakemistolliset = array();
foreach ($palveluhakemistolliset_all as $hakemistollinen) {
	$palveluhakemistolliset[] = $hakemistollinen[0];
}

$all_kos = $sql->query('SELECT DISTINCT koid, db FROM jos_joomla_db_link ORDER BY koid ASC');
$kos_with_editor = $sql->query('SELECT DISTINCT ko FROM jos_koconde_contacts WHERE role = "editor" AND email != "kotikatu@helka.net"');
$kos_with_publiccontact = $sql->query('SELECT DISTINCT ko FROM jos_koconde_contacts WHERE role = "publiccontact" AND email != "kotikatu@helka.net"');
$kos_with_announcements = $sql->query('SELECT DISTINCT ko FROM jos_koconde_contacts WHERE role = "announcements" AND email != "kotikatu@helka.net"');

$kos = array();
while ($ko = $all_kos->fetch_assoc()) {
	$kos[$ko['koid']] = array('name' => $ko['koid']);
	$kos[$ko['koid']]['url'] = 'http://kaupunginosat.net/'.$ko['koid'];
	$kos[$ko['koid']]['db'] = $ko['db'];

	if (substr($ko['koid'], -4, 4) == '_new') {
		$kos[$ko['koid']]['url'] = 'http://kaupunginosat.net/'.substr($ko['koid'], 0, -4).'/new_layout';
		$kos[$ko['koid']]['type'] = 'new_layout';
	}
}


/*********************
 * Poikkeustapaukset *
 *********************/

// Testisivut yms. ei ko-sivut
unset($kos['']);
//unset($kos['koulutus']);
unset($kos['koulutus2']);
//unset($kos['helka']);
//unset($kos['portal']);
unset($kos['testi2']);
unset($kos['testisivu']);
unset($kos['savela_backup']);
unset($kos['osallistuvak']);
//unset($kos['kehitysprojektit']);
unset($kos['paltor']);
//unset($kos['pajamaki_new']);
unset($kos['hevossalmi']);
unset($kos['joomla3']);
unset($kos['new_layout']);
unset($kos['portfolio']);

// Poikkeukselliset url-osoitteet
$kos['itahelsinki']['url'] = "http://kaupunginosat.net/itahelsinginkulttuuri";
$kos['munkinseutu_j17']['url'] = "http://kaupunginosat.net/munkinseutu";
$kos['rantaryhmaUusi']['url'] = "http://kaupunginosat.net/rantaryhma";
$kos['vahameilahti']['url'] = "http://kaupunginosat.net/lillmejlans";
//$kos['joomla3']['url'] = "http://kaupunginosat.net/sandbox/joomla3";
$kos['kehitysprojektit']['url'] = "http://kaupunginosat.net/ruohonkarjet";
$kos['kehitysprojektit']['name'] = "Ruohonk&auml;rjet";
$kos['stake']['url'] = "http://kaupunginosat.net/stake-v";

// Muut kuin kaupunginosasivut
$kos['koulutus']['type'] = 'test';
$kos['helka']['type'] = 'helka';
$kos['portal']['type'] = 'helka';
$kos['kotikatu']['type'] = 'helka';
$kos['kehitysprojektit']['type'] = 'project';
$kos['stake']['type'] = 'project';
$kos['uudistuu']['type'] = 'project';

foreach ($kos as $key => $ko) {
        /**************************
         * Fetch name of the page *
         **************************/
        $conffile = file(str_replace("http://kaupunginosat.net/", "/site/", $ko['url'].'/configuration.php'));
        $dbprefix = 'j17_';
        foreach ($conffile as $conf) {
                if (strstr($conf, '$sitename ')) {
                        $sitename = substr($conf, strpos($conf, "'")+1, strrpos($conf, "'")-strpos($conf, "'")-1);
                }
                if (strstr($conf, '$dbprefix ')) {
                        $dbprefix = substr($conf, strpos($conf, "'")+1, strrpos($conf, "'")-strpos($conf, "'")-1);
                }
        }
	$kos[$key]['sitename'] = $sitename;
	$kos[$key]['dbprefix'] = $dbprefix;

        /*
         * Fetch Joomla! version
         */
        $joomlamanifest = file(str_replace("http://kaupunginosat.net/", "/site/", $kos[$key]['url'].'/administrator/manifests/files/joomla.xml'));
        $version = 'unknown';
        foreach ($joomlamanifest as $manifestrow) {
                if (strstr($manifestrow, '<version>')) {
                        $version = substr($manifestrow, strpos($manifestrow, "<version>")+9, strrpos($conf, "</version>")-(strpos($manifestrow, "<version>")+10));
                        break;
                }
        }
	$kos[$key]['version'] = $version;

        /*
         * Find out situation with the new layout
         */
        $new_layout = 0; // New layout not in use
        if (is_dir(str_replace("http://kaupunginosat.net/", "/site/", $ko['url'].'/new_layout'))) { // New layout is not published yet
		$new_layout = 1;
	}

        $default_template = $sql->query('select template from '.$ko['db'].'.'.$dbprefix.'template_styles where client_id = 0 and home = 1 limit 1;');
	$default_template = $default_template->fetch_assoc();
        $default_template = $default_template['template'];
        if ($default_template == 'helka_blogs') $new_layout = 2;

	$kos[$key]['new_layout'] = $new_layout;

	if ($new_layout == 1) $kos[$key]['type'] = 'old_layout';


	/*
	 * Is koforum component in use? Or Kunena?
	 */
	$koforum = $sql->query('select * from '.$ko['db'].'.'.$dbprefix.'menu where link like "index.php?option=com_koforum%" and published = 1 limit 1;');
	$koforum = $koforum->num_rows;

	$kunena = $sql->query('select * from '.$ko['db'].'.'.$dbprefix.'menu where link like "index.php?option=com_kunena%" and menutype not like "%kunena%" and published = 1 limit 1;');
	$kunena = $kunena->num_rows;

	if ($koforum) $kos[$key]['koforum'] = 'koforum';
	elseif ($kunena) $kos[$key]['koforum'] = 'kunena';
	else $kos[$key]['koforum'] = false;

	/*
	 * Is koforum component in use? Or Kunena?
	 */
	$helkacal = $sql->query('select * from '.$ko['db'].'.'.$dbprefix.'menu where link like "index.php?option=com_helkacal%" and published = 1 and access = 1 limit 1;');
	$helkacal = $helkacal->num_rows;
	$kos[$key]['helkacal'] = $helkacal;

	/*
	 * Are there any spambots registered?
	 */
	$spambots = $sql->query('select * from '.$ko['db'].'.'.$dbprefix.'users where username = "getloans" or username = "inside" or username = "greencoffee" or username = "thatsafunnypic" or username = "edda92" or username = "zenonandwelon" or email like "%.pl" or email like "%.ru" or email like "%pichosti.info" or email like "%.com.ua" or email like "joomlas.xx%" or email like "%@cheap-car-insurance-quotes.cf";');
	$spambots = $spambots->num_rows;

	$kos[$key]['spambots'] = $spambots;

	$kos[$key]['editor'] = 0;
	$kos[$key]['publiccontact'] = 0;
	$kos[$key]['announcements'] = 0;
	$kos[$key]['prefix'] = $dbprefix;

	if (array_search($ko['name'], $palveluhakemistolliset) !== false) $kos[$key]['palveluhakemisto'] = 1;
	else $kos[$key]['palveluhakemisto'] = 0;
}

while ($ko = $kos_with_editor->fetch_assoc()) $kos[$ko['ko']]['editor'] = 1;
while ($ko = $kos_with_publiccontact->fetch_assoc()) $kos[$ko['ko']]['publiccontact'] = 1;
while ($ko = $kos_with_announcements->fetch_assoc()) $kos[$ko['ko']]['announcements'] = 1;

/*
$kos_num=0;
$uptodate_num=0;
$new_layout_num=0;
$new_layout_np_num=0;
$palveluhakemisto_num=0;
$koforum_num=0;
$helkacal_num=0;
$editors_num=0;
$publiccontact_num=0;
$announcements_num=0;
*/

$outp = "";
foreach ($kos as $ko) {
	if (!isset($ko['url'])) {
		continue;
	}

//	$sql->query('UPDATE jos_joomla_db_link SET sitename = "'.$ko['sitename'].'" WHERE db = "'.$ko['db'].'"');

	if ($outp != "") $outp .= ",";

	$outp .= '{"Type":"'.(isset($ko['type']) ? $ko['type'] : 'ko').'",';
	$outp .= '"Sitename":"'.$ko['sitename'].'",';
	$outp .= '"Url":"'.$ko['url'].'",';
	$outp .= '"Version":"'.$ko['version'].'",';
	$outp .= '"NewLayout":'.$ko['new_layout'].',';
	$outp .= '"Palveluhakemisto":'.$ko['palveluhakemisto'].',';
	$outp .= '"KOForum":"'.$ko['koforum'].'",';
	$outp .= '"HelkaCal":'.$ko['helkacal'].',';
	$outp .= '"Editor":'.$ko['editor'].',';
	$outp .= '"PublicContact":'.$ko['publiccontact'].',';
	$outp .= '"Announcements":'.$ko['announcements'].',';
	$outp .= '"Prefix":"'.$ko['prefix'].'",';
	$outp .= '"Spambots":'.$ko['spambots'].'}';

/*	if (!isset($ko['type']) or $ko['type'] == 'old_layout') {
		$kos_num++;
		if ($ko['version'] == $newest_joomla_version) $uptodate_num++;
		if ($ko['new_layout'] == 2) $new_layout_num++;
		elseif ($ko['new_layout'] == 1) $new_layout_np_num++;
		if ($ko['palveluhakemisto']) $palveluhakemisto_num++;
		if ($ko['koforum']) $koforum_num++;
		if ($ko['helkacal']) $helkacal_num++;
		if ($ko['editor']) $editors_num++;
		if ($ko['publiccontact']) $publiccontact_num++;
		if ($ko['announcements']) $announcements_num++;
	}
*/
}

$outp = '{"sites":['.$outp.']}';

header('Content-length: ' . strlen($outp));

echo $outp;

$sql->close();

flush();
ob_flush();
sleep(2);
exit(0);

?>
