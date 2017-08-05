<?
//-----------------------------------------------------------------------------
// $Workfile: stocktrader.php $ $Revision: 1.15 $ $Author: addy $ 
// $Date: 2010/03/02 21:15:10 $
//-----------------------------------------------------------------------------

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'vbtrade');

$phrasegroups = array('help_faq', 'fronthelp');
$specialtemplates = array();
$actiontemplates = array();

$globaltemplates = array('vbtrade_main','forumdisplay_sortarrow');

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR .'/includes/class_stocktrader.php');
require_once(DIR .'/includes/functions_stocktrader.php');

// some global variables
require_once(DIR . '/includes/class_xml.php');
$requester = new StockRequester();
$moneyrow = $vbulletin->options['vbst_moneyrow'];
$userid = $vbulletin->userinfo[userid];

if (empty($_REQUEST['do'])) 
{
	$_REQUEST['do'] = 'main';
}

$vbtgroups = explode(',',$vbulletin->options['vbst_groups']);
if ($vbulletin->options['vbst_moneyrow'] != '' && $vbulletin->options['vbst_onoff'] == 1 
				&& (is_member_of($vbulletin->userinfo,$vbtgroups) OR $vbtgroups[0] == 0))
{
	if ($_REQUEST['do'] == 'lookup')
	{
		$vbulletin->input->clean_array_gpc('r', 
			array('symbol' => TYPE_STR,
		));
		
		// TODO: build this table from templates
		$shareinfo = $requester->GetSingleQuote($vbulletin->GPC['symbol']);

			// pretty up the change info
			if ($shareinfo['change'] == 0)
				$change = "$shareinfo[change] ($shareinfo[perchange])";
			else if ($shareinfo['perchange'] > 0)
				$change = "<font color=green>$shareinfo[change] ($shareinfo[perchange])</font>";
			else if ($shareinfo['perchange'] < 0)
				$change = "<font color=red>$shareinfo[change] ($shareinfo[perchange])</font>";

		$response .= "
<table class='tborder' cellpadding='$stylevar[cellpadding]' cellspacing='$stylevar[cellspacing]' border='0' width='100%' align='center'>
<tbody id='collapseobj_stock_lookup' style='$vbcollapse[collapseobj_stock_lookup]'>	
		<tr><td class='thead'>&nbsp;<b>Stock</b></td><td class='thead' align=center>&nbsp;Last Price</td><td class='thead' align=center>Change</td><td class='thead' align=center>Volume</td><td class='thead' align=center>Last Trade Data & Time</td></tr>
		<tr class='alt1'><td>$shareinfo[name] ($shareinfo[exchange]:$shareinfo[symbol])</td><td align=center>$shareinfo[lasttrade]</td><td align=center>$change</td><td align=center>". number_format($shareinfo[volume]) ."</td><td align=center>$shareinfo[lasttradedate] $shareinfo[lasttradetime]</td></tr>
</tbody>		
</table>			

";
		
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('response');
		$xml->add_tag('tag1',$response);
		$xml->close_group();
		$xml->print_xml();		
		exit;
	}	
	
	$navbits = array('stocktrader.php' . $vbulletin->session->vars['sessionurl_q'] => 'Stock Trader');
	$navbits[""] = "Stock Trader";

	$cashonhand = $vbulletin->userinfo[$moneyrow];
	$cashonhandtxt = vb_number_format($cashonhand);
	$usdonhandtxt = vb_number_format($cashonhand * $vbulletin->options['vbst_xchgrate'],2, '.', '');
	
	if ($_REQUEST['do'] == 'main')
	{
		$stocktable = GetStockTable(true,$userid);
		
		if ($vbulletin->options['vbst_xchgrate'] != 1 && $stocktable != null)
			$stocktable .= "<br><br>".(GetStockTable(false,$userid));	
	}
	else if ($_REQUEST['do'] == 'sellpreview' || $_REQUEST['do'] == 'dosell')
	{
		// make sure trading is open
		if ($vbulletin->options['xk0st_openclose_onoff'])
		{
			if (strstr($vbulletin->options['xk0st_openclose_days'],date("N",mktime())) == false)
			{
				eval(standard_error(fetch_error('xk0st_trading_closed')));			
			}
			
			$curtime = date("Hi",mktime());
			if ($curtime < $vbulletin->options['xk0st_openclose_open'] || $curtime > $vbulletin->options['xk0st_openclose_close'])
			{
				eval(standard_error(fetch_error('xk0st_trading_closed')));			
			}
		}
		
		$vbulletin->input->clean_array_gpc('p', 
				array('stocks' => TYPE_ARRAY,
			));
		
		$formarray = array();
		$wheresql = "";
		foreach ($vbulletin->GPC['stocks'] as $stockid => $sellqty)
		{
			if ($sellqty > 0)
			{
				$wheresql .= "'$stockid',";
				$formarray[$stockid] = $sellqty;
				
			}
		}
		
		if (count($formarray) == 0)
			eval(standard_error(fetch_error('ambst_zero_stock')));		

		$wheresql = '('.substr($wheresql, 0, -1).')';
		$requester_query = array();
		$qtyerror = false;
		$stocksinfo = $db->query_read("SELECT * from ". TABLE_PREFIX ."stocktrader WHERE (userid = '$userid') AND stockid IN $wheresql;");
		while ($stockinfo = $db->fetch_array($stocksinfo))
		{
			array_push($requester_query,$stockinfo['symbol']);
			if ($formarray[$stockinfo['stockid']] > $stockinfo['shares'])
			{
				$qtyerror = true;
				break;
			}				
			
			if ($vbulletin->options['vbst_days'] != 0 && (time() - $stockinfo['purchase_date']) < ($vbulletin->options['vbst_days'] * 24 * 60 * 60))
				eval(standard_error(fetch_error('ambst_invalid_toosoon',$vbulletin->options['vbst_days'])));					
		}
		
		if ($qtyerror)
		{
			eval(standard_error(fetch_error('ambst_invalid_nss')));		
		}

		$curinfo = $requester->GetQuotes($requester_query);
		
		if ($_REQUEST['do'] == 'sellpreview')
		{
			$preview = "
				<form action='stocktrader.php?do=dosell' method='post' name='sellformconf'>			
							
				<table class='tborder' cellpadding='$stylevar[cellpadding]' cellspacing='$stylevar[cellspacing]' border='0' width='100%' align='center'>
					<tr class='tcat'>
						<td colspan=99><b>Sell Preview</b></td>
					</tr>
				";
		}
		
		$alt = '1'; $total_offer = 0;
		$stocksinfo = $db->query_read("SELECT * from ". TABLE_PREFIX ."stocktrader WHERE (userid = '$userid') AND stockid IN $wheresql;");
		while ($stockinfo = $db->fetch_array($stocksinfo))
		{
			// get the data from yahoo's server
			$quote = $curinfo[$stockinfo['symbol']];
			$qty4sale = $formarray[$stockinfo['stockid']];
			
			$lasttrade_fc = $quote[lasttrade] * 1/$vbulletin->options['vbst_xchgrate'];
			
			$mrktval = $qty4sale * $quote[lasttrade];
			$mrktval_fc = ($mrktval * 1/$vbulletin->options['vbst_xchgrate']);
			
			$comper = (1/$vbulletin->options['vbst_xchgrate']) * ($mrktval * ($vbulletin->options['vbst_com_sellper'] * .01));
			$feecost_fc = $comper + $vbulletin->options['vbst_com_sellfr'];
			
			$cgt_fc = 0;
			if ($vbulletin->options['vbst_cgt'] > 0)
			{
				// how much it cost, in forum currency to purchase the amount of stocks being sold
				$purchasprice_fc = ($stockinfo['purchase_price'] * $qty4sale) * (1/$vbulletin->options['vbst_xchgrate']);
				
				// only charge a capital gains tax if the user is making a profict
				if ($mrktval_fc > $purchasprice_fc)
					$cgt_fc = ($mrktval_fc - $purchasprice_fc) * ($vbulletin->options['vbst_cgt'] * .01);
			}
			
			$subtotal_fc = ($mrktval_fc - ($feecost_fc + $cgt_fc));
			$total_offer += $subtotal_fc;
			
			if ($_REQUEST['do'] == 'sellpreview')
			{
				$preview .= "
				<input type=hidden name='stocks[$stockinfo[stockid]]' value='$qty4sale'>
				<tr><td colspan=99 class='inlinemod'><b>$quote[name]</b> ($quote[exchange]:$quote[symbol])</td></tr>
				<tr><td class='thead' colspan=2>&nbsp</td><td class='thead' align=center>USD</td><td class='thead' align=center>Forum Currency</td></tr>
				<tr class='alt1'><td>&nbsp;</td><td>Last Trading Price</td><td align=right>". number_format($quote[lasttrade],3) ."</td><td align=right>". number_format($lasttrade_fc,3) ."</td></tr>
				<tr class='alt". (ToggleAlt(&$alt)). "'><td>&nbsp;</td><td>Market Value of <i>$qty4sale</i> shares</td><td align=right>". number_format($mrktval,3) ."</td><td align=right>". number_format($mrktval_fc,3) ."</td></tr>";
				
				if ($feecost_fc > 0)
					$preview .= "<tr class='alt". (ToggleAlt($alt)). "'><td>&nbsp;</td><td>Commission</td><td>&nbsp;</td><td align=right>". number_format($feecost_fc,3) ."</td></tr";
				
				if ($cgt_fc > 0)
						$preview .= "<tr class='alt". (ToggleAlt($alt)). "'><td>&nbsp;</td><td>Capital Gains Tax</td><td>&nbsp;</td><td align=right>". number_format($cgt_fc,3) ."</td></tr>";
				
				
				$preview .= "<tr class='alt". (ToggleAlt($alt)). "'><td>&nbsp;</td><td><b>Total Sale Amount</b></td><td>&nbsp;</td><td align=right>". number_format($subtotal_fc,3) ."</td></tr>";
			}
			elseif ($_REQUEST['do'] == 'dosell') 
			{
				// set the new amount of stock this user owns
				$stockinfo['shares'] -= $qty4sale;
				
				// if they sold everything, delete the entry
				if ($stockinfo['shares'] == 0)
				{
					$db->query_write("DELETE FROM " . TABLE_PREFIX . "stocktrader WHERE (stockid = '$stockinfo[stockid]')");
				}
				// else update with the new quantity
				elseif ($stockinfo['shares'] > 0)
				{
					$db->query_write("UPDATE ". TABLE_PREFIX ."stocktrader SET shares='$stockinfo[shares]' WHERE (stockid = '$stockinfo[stockid]');");
				}
				
				LogAction('SOLD',$stockinfo['symbol'],$qty4sale,$quote['lasttrade']);
			}
		}

		if ($_REQUEST['do'] == 'sellpreview')
		{
			$preview .="
				<tr><td colspan=99 align=right class='inlinemod'><b>TOTAL OFFER</b> &nbsp; &nbsp;". number_format($total_offer,3) ."</td></tr>
				<tr class=alt2><td colspan=99 align=right><input type=submit style=button value='Sell Shares'></td></tr>			
			</table>
			</form>";			
		}
		elseif ($_REQUEST['do'] == 'dosell') 
		{
			// update the user table			
			$db->query("update " . TABLE_PREFIX . "user set {$vbulletin->options['vbst_moneyrow']}={$vbulletin->options['vbst_moneyrow']}+'{$total_offer}' where userid='{$vbulletin->userinfo['userid']}'");
			
			exec_header_redirect('stocktrader.php?do=main');				
		}
	}
	else if ($_REQUEST['do'] == 'previewpurchase' || $_REQUEST['do'] == 'purchase')
	{
		// make sure trading is open
		if ($vbulletin->options['xk0st_openclose_onoff'])
		{
			if (strstr($vbulletin->options['xk0st_openclose_days'],date("N",mktime())) == false)
			{
				eval(standard_error(fetch_error('xk0st_trading_closed')));			
			}
			
			$curtime = date("Hi",mktime());
			if ($curtime < $vbulletin->options['xk0st_openclose_open'] || $curtime > $vbulletin->options['xk0st_openclose_close'])
			{
				eval(standard_error(fetch_error('xk0st_trading_closed')));			
			}
		}
	
		$vbulletin->input->clean_array_gpc('p', 
			array('symbol' => TYPE_STR,
					'shares' => TYPE_INT,
					'shareprice' => TYPE_STR,		
		));
		
		$symbol = strtoupper($vbulletin->GPC['symbol']);
		$shares = $vbulletin->GPC['shares'];
		$shareprice = $vbulletin->GPC['shareprice'];		
	
		// check the allow list
		if ($vbulletin->options['xk0st_stock_list_use'] == 1)
		{
			// only these stocks
			$list = explode("\r\n",$vbulletin->options['xk0st_stock_list']);
			
			if (!in_array($symbol,$list))
			{
				eval(standard_error(fetch_error('xk0st_stock_not_allowed')));			
			}
		}
		else if ($vbulletin->options['xk0st_stock_list_use'] == 2)
		{
			// do not allow these stocks
			$list = explode("\r\n",$vbulletin->options['xk0st_stock_list']);
			
			if (in_array($symbol,$list))
			{
				eval(standard_error(fetch_error('xk0st_stock_prohibited')));			
			}
		}

		if ($shares < 1) 
		{
			eval(standard_error(fetch_error('ambst_invalid_shares')));		
		}
		
		if ($vbulletin->options['vbst_maxstock'] > 0)
		{
			$usershares = array();
			$tempshares = $db->query("SELECT DISTINCT(symbol)
														FROM stocktrader
														WHERE (userid = ".$vbulletin->userinfo['userid'].")
													");
			
			$count = 0;
			while ($share = $db->fetch_array($tempshares))
			{
				$count = array_push($usershares,$share['symbol']);		
			}
			
			if ($count >= $vbulletin->options['vbst_maxstock'] && !in_array($symbol,$usershares))
			{
				eval(standard_error(fetch_error('xk0st_maxstock_error')));			
			}
		}
			
		if (is_string($symbol))
		{
			$quote = $requester->GetSingleQuote($symbol);
		}
		
		// protect against buying REALLY REALLY cheap stock
		if ($quote[lasttrade] < $vbulletin->options['vbst_minshareprice'])
		{
			eval(standard_error(fetch_error('ambst_invalid_shares_buy')));
		}
			
		//if (!is_numeric($quote['volume']))
		if (!is_numeric($quote['lasttrade']))
		{
			eval(standard_error(fetch_error('invalid_stock')));	
		}

		// share cost in USD and in Forum Currency
		$sharecost = $quote['lasttrade'] * $shares;
		$sharecost_fc = $sharecost * (1/$vbulletin->options['vbst_xchgrate']);
		
		// compute the percentage commission in USD and convert to forum currency
		$comper = (1/$vbulletin->options['vbst_xchgrate']) * ($sharecost * ($vbulletin->options['vbst_com_purper'] * .01));
		$feecost_fc = $comper + $vbulletin->options['vbst_com_purfr'];
		
		// comptuer the total purchase price in forum currency 
		$totalcost_fc = $sharecost_fc + $feecost_fc;
		
		if ($totalcost_fc > $cashonhand)
		{
			eval(standard_error(fetch_error('ambst_invalid_nsf')));	
		}
		
		if ($_REQUEST['do'] == 'previewpurchase')
		{
			$preview = "
<form action='stocktrader.php?do=purchase' method='post' name='purchaseformconf'>
<input type='hidden' name='symbol' value='$quote[symbol]'>
<input type='hidden' name='shareprice' value='$quote[lasttrade]'>
<input type='hidden' name='shares' value='$shares'>

<table class='tborder' cellpadding='$stylevar[cellpadding]' cellspacing='$stylevar[cellspacing]' border='0' width='100%' align='center'>
	<tr class='tcat'>
		<td colspan=3><b>$vbphrase[ambst_purchasepreview]</b></td>
	</tr>
	<tr class='alt1'><td colspan=3><b>$quote[name]</b> ($quote[exchange]:$quote[symbol])</td></tr>
	<tr><td class='thead'>&nbsp;</td><td class='thead' align=center>&nbsp;USD</td><td class='thead' align=center>&nbsp;Forum Currency</td></tr>
	
	<tr class='alt2'><td>Last Trading Price</b></td><td align=right>\$$quote[lasttrade]</td><td align=right>".(number_format($quote[lasttrade] * (1/$vbulletin->options['vbst_xchgrate']),3))."</td></tr>
	<tr class='alt1'><td><i>$shares</i> share(s)</b></td><td align=right>\$".(number_format($sharecost,3))."</td><td align=right>".(number_format($sharecost_fc,3))."</td></tr>
	<tr class='alt2'><td>Commission</td><td align=right>&nbsp;</td><td align=right>".(number_format($feecost_fc,3))."</td></tr>
	<tr class='alt1'><td><b>Total</b></td><td align=right>&nbsp;</td><td align=right>".(number_format($totalcost_fc,3))."</td></tr>
	<tr class='alt2'><td colspan=3 align=right><input type=submit class=button value='Purchase'></td></tr>
	";
	
			if ($vbulletin->options['vbst_days'] > 0)
			{
				$days = $vbulletin->options['vbst_days'];
				$preview .= "<tr class='inlinemod'><td><b>Note!</b> If purchased, you will not be able to sell this stock for $days days.</td></tr>";
			}
		
			$preview .= "</table></form>";
		}
		else
		{
			// update the user table			
			$db->query("update " . TABLE_PREFIX . "user set {$vbulletin->options['vbst_moneyrow']}={$vbulletin->options['vbst_moneyrow']}-'{$totalcost_fc}' where userid='{$vbulletin->userinfo['userid']}'"); 
			
			// check to see if the user already owwns this stock
			$temp = $db->query_first("
								SELECT stockid
								FROM " . TABLE_PREFIX . "stocktrader AS stocktrader
								WHERE (symbol = '$symbol') AND (userid = $userid);
							");
			
			// user already owns this stock, update it				
			if ($temp['stockid'] > 0)
			{
				$db->query_write("
								UPDATE
								". TABLE_PREFIX ."stocktrader 
								SET shares = (shares + $shares)
								WHERE (stockid = $temp[stockid]);
							");
			}
			else
			{							
				$db->query_write("INSERT INTO ". TABLE_PREFIX ."stocktrader (userid,symbol,purchase_price,shares,purchase_date) VALUES ('$userid','$symbol','$shareprice','$shares',UNIX_TIMESTAMP(NOW()));");
			}
		
			LogAction('PURCHASED',$symbol,$shares,$shareprice);
			exec_header_redirect('stocktrader.php?do=main');
		}
	}
	else if ($_REQUEST['do'] == 'topportfolios')
	{
		$vbulletin->input->clean_array_gpc('r', 
			array('sort' => TYPE_STR,
			'order' => TYPE_STR,
		));	
		
		$sortfield = mysql_escape_string($vbulletin->GPC['sort']);
		$sortorder = mysql_escape_string($vbulletin->GPC['order']);
		
		if ($sortfield == '')
			$sortfield = 'ambportgainamt';
			
		if ($sortorder == '')
			$sortorder = 'desc';	
		
		$sorturl = 'stocktrader.php?' . $vbulletin->session->vars['sessionurl'] . "do=topportfolios&amp;";
		$pagenav = construct_page_nav(1, 20, 20, $sorturl . "&amp;sort=$sortfield" . (!empty($sortorder) ? "&amp;order=$sortorder" : ""));
		$oppositesort = ($sortorder == 'asc' ? 'desc' : 'asc');		
		eval('$sortarrow[' . $sortfield . '] = "' . fetch_template('forumdisplay_sortarrow') . '";');
				
		$userinfo_sql = $db->query_read("SELECT user.userid,user.username,user.ambportval, user.ambportgainamt, user.ambportgainper FROM ". TABLE_PREFIX ."user AS user WHERE (user.ambportval > 0) ORDER BY user.$sortfield $sortorder LIMIT 20;");
		
		$preview .= "<table class='tborder' cellpadding='$stylevar[cellpadding]' cellspacing='$stylevar[cellspacing]' border='0' width='100%' align='center'>\n";
		$preview .= "<tr><td class='thead'>&nbsp;</td><td class='thead'>&nbsp;<a href='stocktrader.php?do=topportfolios&order=asc&sort=username'>Member</a> $sortarrow[username]</td><td class='thead' align=center>&nbsp;<a href='stocktrader.php?do=topportfolios&order=desc&sort=ambportval'>Portfolio Value</a> $sortarrow[ambportval]</td><td class='thead' align=center><a href='stocktrader.php?do=topportfolios&order=desc&sort=ambportgainamt'>Total Earnings</a> $sortarrow[ambportgainamt]</td><td class=thead align=center><a href='stocktrader.php?do=topportfolios&order=desc&sort=ambportgainper'>Earning Percentage</a> $sortarrow[ambportgainper]</td></tr>";
		
		while ($userinfo = $db->fetch_array($userinfo_sql))
		{
			$portvaltext = number_format($userinfo[ambportval] * (1/$vbulletin->options['vbst_xchgrate']),2);
			$earningtext = number_format($userinfo[ambportgainamt] * (1/$vbulletin->options['vbst_xchgrate']),2);
			$percenttext = number_format($userinfo[ambportgainper] * 100,2);
			
			if ($vbulletin->options['vbst_xchgrate'] != 1)
			{
				$portvaltext = "$portvaltext (".(number_format($userinfo[ambportval],2))." USD)";
				$earningtext = "$earningtext (".(number_format($userinfo[ambportgainamt],2))." USD)";
			}
			$preview .= "<tr><td class='alt1' align='center'><a href='stocktrader.php?do=viewportfolio&amp;userid=$userinfo[userid]'><img src='images/misc/portfolio.gif' border='0' alt='View User Portfolio'></a></td><td class='alt2'><a href='member.php?u=$userinfo[userid]'>$userinfo[username]</a></td><td class='alt1' align=right>$portvaltext</td><td class='alt2' align=right>$earningtext<td class='alt1' align=center>$percenttext%</td></tr>";	
		}
		
		$preview .= "</table>\n";
	}
	else if ($_REQUEST['do'] == 'viewportfolio')
	{
		$vbulletin->input->clean_array_gpc('r', 
			array('userid' => TYPE_STR,
		));
		
		$viewuser = $vbulletin->GPC['userid'];
		$info = $db->query_first("SELECT username FROM ". TABLE_PREFIX ."user WHERE (userid = '$viewuser');");
		
		$stocktable .= "	
			<table class='tborder' cellpadding='$stylevar[cellpadding]' cellspacing='$stylevar[cellspacing]' border='0' width='100%' align='center'>
				<tr class='tcat'>
					<td colspan=99 align=center><b>$info[username]'s Portfolio</b></td>
				</tr>		
			</table><br>";		
		
		$stocktable .= GetStockTable(true,$viewuser,false);
		
		if ($vbulletin->options['vbst_xchgrate'] != 1 && $stocktable != null)
			$stocktable .= "<br>".(GetStockTable(false,$viewuser,false));			
	}	
	else if ($_REQUEST['do'] == 'showhistory')
	{
		$userid = $vbulletin->userinfo['userid'];
		$username = $vbulletin->userinfo['username'];
		
		$transations = $db->query("
										SELECT *
										FROM " . TABLE_PREFIX . "stockhistory as stockhistory
										WHERE (userid = $userid)	
										ORDER BY dateline DESC;	
									");
		$stocktable .= "					
<table class='tborder' cellpadding='$stylevar[cellpadding]' cellspacing='$stylevar[cellspacing]' border='0' width='100%' align='center'>
	<tr class='tcat'>
		<td colspan=99>
			<b>Transaction History for $username</b>
		</td>
	</tr>	
	
	<tr><td class='thead'>&nbsp;Action</td><td class='thead' align=center>Stock</td><td class='thead' align=center>&nbsp;Price</td><td class='thead' align='center'>Quantity</td><td class='thead' align=center>&nbsp;Transaction Date</td></tr>
		";
			
		$alt = 1;									
		while ($transaction = $db->fetch_array($transations))
		{
			$stocktable .= "
					<tr class='alt$alt'>
						<td>$transaction[action]</td>
						<td>$transaction[symbol]</td>
						<td>$transaction[price]</td>
						<td>$transaction[quantity]</td>
						<td>$transaction[dateline]</td>
					</tr>
				";
		}
		
		$stocktable .= "</table>";	
		$alt == 1 ? $alt = 2 : $alt = 1;	
	}
	else if ($_REQUEST['do'] == 'resetgameconfirm' || $_REQUEST['do'] == 'resetgame' && is_member_of($vbulletin->userinfo, 6))
	{
		if ($_REQUEST['do'] == 'resetgameconfirm')
		{		
			$navbits = construct_navbits($navbits);
			eval('$navbar = "' . fetch_template('navbar') . '";');
			eval('print_output("' . fetch_template('xk0st_reset_confirm') . '");');
			exit;			
		}
		else if ($_REQUEST['do'] == 'resetgame')
		{
			if (isset($_REQUEST['confirm']))
			{
				$db->query("DELETE FROM " . TABLE_PREFIX . "stocktrader");
				$db->query("DELETE FROM " . TABLE_PREFIX . "stockhistory");
			}

			exec_header_redirect('stocktrader.php');
		}
		
	}
	
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('vbtrade_main') . '");');
}
else
{
	eval(standard_error(fetch_error('invalid_action')));
}


?>