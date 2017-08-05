<?php
// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'boardstats');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

$countcols = array(
	'postcount',
	'threadcount',
	'membercount'
	);
	
if (empty($_REQUEST['do'])) 
{
	$_REQUEST['do'] = 'main';
}

$navbits = array('boardstats.php' . $vbulletin->session->vars['sessionurl_q'] => 'BoardSpy Stats');
$navbits[""] = "BoardSpy Stats";
$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');


if ($_REQUEST['do'] == 'main')
{
	$today = getdate((TIMENOW - $vbulletin->options['hourdiff'])-60*60*24);
	$today['month'] = $vbphrase[strtolower($today['month'])];

	$dayselected = $today['mday'];
	$monthselected["$today[mon]"] = 'selected="selected"';
	$yearselected["$today[year]"] = 'selected="selected"';
	
	$startyear = date("Y");
	$yearbits = '';
	for ($gyear = $startyear-10; $gyear <= $startyear+1; $gyear++)
	{
		$yearbits .= "\t\t<option value=\"$gyear\" $yearselected[$gyear]>$gyear</option>";
	}	
	
	eval('print_output("' . fetch_template('boardstats_main') . '");');	
}
else if ($_REQUEST['do'] == 'showstats')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'month'       => TYPE_STR,	
		'day'       => TYPE_STR,	
		'year'       => TYPE_STR
		));

	$curdate = intval(mktime(0, 0, 0, $vbulletin->GPC['month'], $vbulletin->GPC['day'], $vbulletin->GPC['year']));	
	$nextdate = $curdate + 60 * 60 * 24;

	// get the total stats like we want	
	$totals = array();
	$localinfo = $db->query_first("
					SELECT * 
					FROM stats
					WHERE FROM_UNIXTIME(dateline,'%Y-%m-%d') = FROM_UNIXTIME($curdate,'%Y-%m-%d')
				");

	$totals[-1]['postcount'] = $localinfo['npost'];
	$totals[-1]['threadcount'] = $localinfo['nthread'];
	$totals[-1]['membercount'] = $localinfo['nuser'];
			
	foreach($countcols as $column)
	{
		$curarray = array();
		$nextarray = array();
		
		$curdatesql = "
					SELECT
						boardspyboardid,
						MIN(dateline),
						$column AS curcount
					FROM boardspystats
					JOIN boardspyboard USING (boardspyboardid)
					WHERE
						DATE(dateline) = FROM_UNIXTIME($curdate,'%Y-%m-%d')
					GROUP BY boardspyboardid
			";
		
		$curdata = $db->query_read($curdatesql);
		while ($data = $db->fetch_array($curdata))
		{
			$boardid = $data['boardspyboardid'];
			$curarray[$boardid] = $data['curcount'];
		}
			
		$nextdatesql = "
					SELECT
						boardspyboardid,
						MIN(dateline),
						$column AS nextcount
					FROM boardspystats
					JOIN boardspyboard USING (boardspyboardid)
					WHERE
						DATE(dateline) = FROM_UNIXTIME($nextdate,'%Y-%m-%d')
					GROUP BY boardspyboardid						
			";	
		
		$nextdata = $db->query_read($nextdatesql);
		while ($data = $db->fetch_array($nextdata))
		{
			$boardid = $data['boardspyboardid'];
			$totals[$boardid][$column] = $data['nextcount'] - $curarray[$boardid];
		}
		
		
	}
	
	// get the board info how we want it
	$boardinfoarray = array();
	$boardinfoarray[-1]['name'] = $vbulletin->options['bbtitle'];
	$boardinfoarray[-1]['url'] = $vbulletin->options['bburl'];	
	
	$boards = $db->query("SELECT * FROM boardspyboard ORDER BY name");
	while ($boardinfo = $db->fetch_array($boards))	
	{
		$id = $boardinfo['boardspyboardid'];
		$boardinfoarray[$id]['name'] = $boardinfo['name'];
		$boardinfoarray[$id]['url'] = $boardinfo['url'];
	}
	
	// sort by post count
	$postsorted = array();
	foreach ($totals as $key => $val)
	{
		$postsorted[$key] = $val['postcount'];
	}
	arsort($postsorted);
	
	// now show the by post count table
	$statsresults .=  "
		<table class='tborder' cellpadding='$stylevar[cellpadding]' cellspacing='$stylevar[cellspacing]' border='0' width='100%' align='center'>\r\n
			<tr class=\"tcat\"><td align=\"center\" colspan=\"4\"><b>".$vbulletin->GPC['year']."-".$vbulletin->GPC['month']."-".$vbulletin->GPC['day']." by New Posts</b></td></tr>
			<tr class='thead'>
				<td>Board Name</td>
				<td>New Posts</td>
				<td>New Threads</td>
				<td>New Members</td>
			</tr>
		";	
	foreach ($postsorted as $boardid => $foo)
	{
		$boardinfo = $boardinfoarray[$boardid];

		$statsresults .= "
			<tr class='alt1'>
				<td><a href=\"$boardinfo[url]\" target=\"_blank\">$boardinfo[name]</a></td>";
		
		
		foreach($countcols as $column)		
		{
			$statsresults .= "<td>".$totals[$boardid][$column]."</td>\r\n";
		}
				
		$statsresults .= "</tr>";		
		
	}
	$statsresults .= ("</table><br/><br/>");	
	
	$today = getdate((TIMENOW - $vbulletin->options['hourdiff'])-60*60*24);
	$today['month'] = $vbphrase[strtolower($today['month'])];

	$dayselected = $today['mday'];
	$monthselected["$today[mon]"] = 'selected="selected"';
	$yearselected["$today[year]"] = 'selected="selected"';
	
	$startyear = date("Y");
	$yearbits = '';
	for ($gyear = $startyear-10; $gyear <= $startyear+1; $gyear++)
	{
		$yearbits .= "\t\t<option value=\"$gyear\" $yearselected[$gyear]>$gyear</option>";
	}	
	
	eval('print_output("' . fetch_template('boardstats_main') . '");');		

		
		
}

?>