<?php
/*
Plugin Name: WP Seo Spy
Plugin URI: http://www.laliamos.com/2011/07/07/seo-spy-comprueba-tu-ranking-diario-de-google-directamente-en-el-interior-de-tu-wordpress/
Description: En pocas palabras, Seo Spy comprueba su ranking diario de Google directamente en el interior de Wordpress.
Author: LaLiamos Estudio Design SEO
Author URI: http://www.laliamos.com
Version: 3.1
License: GPL2
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=KBRBCP9YD9EEL
Tags: google, ranking, position, seo, statistics, google position, search engine 
*/

/*  Copyright YEAR  PLUGIN_AUTHOR_NAME  (email : LaLiamos Estudio Design SEO)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


$plugin_dir = basename(dirname(__FILE__));
load_plugin_textdomain( 'seowatcher', 'wp-content/plugins/' . $plugin_dir, $plugin_dir );

if ('insert' == $_POST['action'])
{
	seowatcher_option_page_process();
}

if ('update' == $_POST['action'])
{
	seowatcher_update_keyworddata();
}


function seowatcher_setup() {
	/* Creating SeoC DB Tables if they don't exist */
	global $wpdb;
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	
	$time = time();
	$start_time = mktime(0, 0, 0, date('m', $time),date('d', $time),date('Y', $time));
	
	//wp_schedule_event($start_time, 'daily', 'seoc_cronjob');
	
	if($wpdb->get_var("show tables like '$seocKeyworddata'") != $seocKeyworddata) {
		
		$sql = "CREATE TABLE " . $seocKeyworddata . " (
				`id` BIGINT( 100 ) NOT NULL AUTO_INCREMENT ,
				`keyword` VARCHAR( 255 ) NOT NULL ,
				`page` VARCHAR( 255 ) NOT NULL ,
				`rank` INT( 10 ) NOT NULL ,
				`time` DATE NOT NULL ,
				PRIMARY KEY ( `id` )
				);";
		$wpdb->query($sql);
	}
	#add_option('seocKeyword', array('seo barato'));
	#add_option('seocUrl', array('www.laliamos.com'));
	#add_option('seocRef', 'true');
	#add_option('seocServer', __('google.es', 'seowatcher'));	
}

function seowatcher_uninstall() {
	global $wpdb;
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	
	//wp_clear_scheduled_hook('seoc_cronjob');	
	//$wpdb->query("DROP TABLE " . $seocKeyworddata);
}

function seowatcher_update_keyworddata() {
	global $seocMessage;
	global $wpdb;
	
	$seocMessage = '<div class="updated fade below-h2" id="message" style="background-color: rgb(255, 251, 204);"><p>' . __('Se inició <b>Actualizar</b>.', 'seowatcher') . '</p></div>';
	
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	
	$wpdb->query("DELETE FROM " . $seocKeyworddata . " WHERE time = CURDATE()");
	
	$keywords = get_option('seocKeyword');
	$url = get_option('seocUrl');
	$foundurls = array();
	$options['headers'] = array(
			'User-Agent' => 'Mozilla/5.0 (Windows; U; Windows NT 6.0; de; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.7'
			);
	foreach ($keywords as $keyword)
	{
		$startpage = 0;
		while (($startpage <= 90) && (count($foundurls[urlencode($keyword)]) < count($url)))
		{
			$requrl = 'http://www.' . get_option("seocServer") .  '/search?hl=de&q='. urlencode($keyword) . '&start=' . $startpage . '&sa=N';
			$response =  wp_remote_request($requrl, $options);
			
			$try = 1;
			while((wp_remote_retrieve_response_code($response) != 200) and ($try <= 3))
			{
				$response =  wp_remote_request($requrl, $options);
				$try++;	
			}

			// Get Results
			preg_match_all("|<li class=g>(.+)<!--n-->|U", $response['body'], $entrys);
			$localentry = 0;
			foreach ($entrys[1] as $entry)
			{
				//Filter Urls and exclude Google Special Pages
				$entry = preg_replace("|" . get_option("seocServer") . "/products|U", "shopping." . get_option("seocServer") .'/', $entry);

				preg_match("|http://(.+)\"|U", $entry, $entryurl);
				if (!preg_match("!(search." . get_option("seocServer") . "|news." . get_option("seocServer")  ."|shopping." . get_option("seocServer")  .")!", $entryurl[1]))
				{
					$localentry++;
					$entryurl = strtolower($entryurl[1]);
					//$entryurl = 'de.wikipedia.org/wiki/Computer';
					//Build Match pattern for Userdefined Pages
					$sitepattern = '!(';
					$spacer = '';
					$rules = false;
					foreach ($url as $togeturl)
					{	
						// Exclude found Urls
						if (!isset($foundurls[urlencode($keyword)][$togeturl]))
						{
							$rules = true;
							$sitepattern .= $spacer . strtolower($togeturl);
							$spacer = '|';
						} 				
					}
					if (!$rules)
					{
						break;
					}
					$sitepattern .= ')!';
					// Save Matches
					if(preg_match($sitepattern, $entryurl, $pattern)) {
						$keyrank = $startpage + $localentry;
						$foundurls[urlencode($keyword)][$pattern[1]] = $keyrank;
						$wpdb->query("INSERT INTO " . $seocKeyworddata . "( keyword, page, rank, time )	VALUES ( '" . $keyword . "' , '" . $pattern[1] . "' , '" . $keyrank . "' , CURDATE() )");
						
					}
					
				}
				
			}
			$startpage = $startpage + 10;
		}
	}
}

function array_trim($var) {
	if (is_array($var))
		return array_map("array_trim", $var);
	if (is_string($var))
		return trim($var);
	return $var;
}

function seowatcher_explode_comma_list($text) {
	$array = explode(',', $text);
	unset($array[6]);
	return array_trim($array);
}

function seowatcher_supportlink() {
	echo "<div style='text-align:center;'><a href=\"http://www.laliamos.com\" alt=\"Posicionamiento web SEO\">Posicionamiento web SEO</a></div>\n";
}

function seowatcher_dashboard_content() {
	global $wpdb;
	$seocKeyworddata = $wpdb->prefix . 'seocKeyworddata';
	$resultlist = $wpdb->get_results("SELECT main.*, (SELECT rank FROM " . $seocKeyworddata . " WHERE time = DATE_SUB(CURDATE(),INTERVAL 1 DAY) and main.keyword = keyword and main.page = page  ORDER by time ASC LIMIT 1 ) as yesterday, (SELECT rank FROM " . $seocKeyworddata . " WHERE time = DATE_SUB(CURDATE(),INTERVAL 7 DAY) and main.keyword = keyword and main.page = page ORDER by time ASC LIMIT 1 ) as befweek FROM " . $seocKeyworddata . " as main WHERE time = CURDATE() ORDER BY keyword, page ASC");
	
	?>
	<div id="dashboard_right_now">
	<p class="sub"><?php _e('Clasificación en Google', 'seowatcher'); ?></p>
	<div class="table">
	<table>
	<tbody>
	<tr class="first">
	<td class="first t pages" style="text-align:left;"><b><?php _e('Palabra clave', 'seowatcher'); ?></b></td>
	<td class="first t pages"> <b><?php _e('URL', 'seowatcher'); ?></b> </td>
	<td class="first t pages" style="text-align:right;width:90px;"> <b><?php _e('Semana pasada', 'seowatcher'); ?></b> </td>
	<td class="first t pages" style="text-align:right;"> <b><?php _e('Ayer', 'seowatcher'); ?></b> </td>
	<td class="first t pages" style="text-align:right;"> <b><?php _e('Hoy', 'seowatcher'); ?></b> </td>
	</tr>
	<?
	foreach ($resultlist as $result)
	{
		if ($result->yesterday <= 0)
		{
			$result->yesterday = '-';
		}
		$startpage = (floor($result->rank/10))*10;
		
		echo '<tr><td class="first t pages" style="text-align:left;width:100px;">' . $result->keyword . '</td><td class="t pages"><a target="_blank" href="http://www.' . get_option("seocServer") .  '/search?q=' . $result->keyword . '&start=' . $startpage . '&sa=N">' .$result->page . ' </td><td class="b" style="">' .  $result->befweek . '</td><td class="b" style="">' .  $result->yesterday . '</td><td class="b" style="color:red;">'  .  $result->rank . '</td></tr>'; 
	}
	?>
	</tbody></table>
	<form name="form1" method="post" action="<?=$location ?>">
	<?php _e('<p><b>Actualizar</b> con demasiada frecuencia puede conducir a <b>problemas</b>!</p>', 'seowatcher'); ?>
	<input type="submit" class="button-primary" value="<?php _e('Update'); ?>" />
	<input name="action" value="update" type="hidden" />
	</form>
	</div>
	
	</div>
	<?php
}

function seowatcher_option_page_process() {
	global $seocMessage;
	$seocMessage = '<div class="updated fade below-h2" id="message" style="background-color: rgb(255, 251, 204);"><p>' . __('Opciones actualizadas.', 'seowatcher') .  '</p></div>';
	
	update_option('seocKeyword', seowatcher_explode_comma_list($_POST['seocKeyword']));
	update_option('seocUrl', seowatcher_explode_comma_list(strtolower($_POST['seocUrl'])));
	if ($_POST['seocServer'] == 'google.es') {
		update_option('seocServer', 'google.es');
	} elseif ($_POST['seocServer'] == 'google.com') {
		update_option('seocServer', 'google.com');
	} elseif ($_POST['seocServer'] == 'own')
	{
		if (preg_match("!^google\.(.+)!",$_POST['seocServerOwn']))
		{
			update_option('seocServer', $_POST['seocServerOwn']);
		} else {
			$seocMessage = '<div class="updated fade below-h2" id="message" style="background-color: rgb(255, 251, 204);"><p>' . __('Error del otro servidor.', 'seowatcher') .  '</p></div>';
		}
		
	}
	if ($_POST['seocRef'] == 'on')
	{
		update_option('seocRef', 'true');
	} else {
		update_option('seocRef', 'false');
	}
	
}

function seowatcher_option_page_content() {
	global $seocMessage;
	?>
	<div id="post-body" class="has-sidebar">
	<div id="post-body-content" class="has-sidebar-content">
	<div class="wrap">
	<form name="form1" method="post" action="<?=$location ?>">
	<h2><?php _e('Seo Spy Opciones', 'seowatcher'); ?></h2>
	<?=$seocMessage; ?>
	<br>
	<div id="keyworddiv" class="postbox" style="max-width:100%;"><h3 class="hndle"><span><?php _e('Palabras clave', 'seowatcher'); ?></span></h3>
	<div class="inside">
	<p><label for="trackback_url"><?php _e('Palabras clave para comprobar:', 'seowatcher'); ?></label> <input name="seocKeyword" style="width:400px;" size="150" id="seocKeyword" tabindex="1" value="<?=implode(', ', get_option("seocKeyword"));?>" type="text"><br>
	<?php _e('(Palabras múltiples claves separadas por comas.) </p><p>Cada una de estas palabras clave será leída por cada URL única, por lo que se puede comparar el ranking de Google con otros sitios web.</p><p><b>Se pueden definir como máximo 6 palabras clave.</b></p>', 'seowatcher'); ?>
	</div>
	</div>
	<div id="keyworddiv" class="postbox" style="max-width:100%;""><h3 class="hndle"><span><?php _e('URLs', 'seowatcher'); ?></span></h3>
	<div class="inside">
	<p><label for="seocUrl"><?php _e('URLs para comprobar:', 'seowatcher'); ?></label> <input name="seocUrl" style="width:300px;" size="150"  id="seocUrl" tabindex="1" value="<?=implode(', ', get_option("seocUrl"));?>" type="text"><br> 
	<?php _e('(URLs separadas por comas.)</p><p> La URL tiene que ser introducido por el patrón o subdomain.domain.com domain.com.</p><p><b>Se pueden definir como máximo 6 URLs.(SE RECOMIENDA 1)</b></p>', 'seowatcher'); ?>
	</div>
	</div>
	


	<div id="keyworddiv" class="postbox" style="max-width:100%;""><h3 class="hndle"><span><?php _e('Google Server:', 'seowatcher'); ?></span></h3>
	<div class="inside">
	<p><label for="seocServer"><br />
		<input type="radio" name="seocServer" value="google.es" <?php if(get_option("seocServer") == 'google.es') { echo 'checked = "true"';} ?>> google.es<br>
    <input type="radio" name="seocServer" value="google.com" <?php if(get_option("seocServer") == 'google.com') { echo 'checked = "true"';} ?>> google.com<br>
    <input type="radio" name="seocServer" value="own" <?php if(get_option("seocServer") != 'google.com' AND get_option("seocServer") != 'google.es') { echo 'checked = "true"';} ?>> <?php _e('otro server:', 'seowatcher'); ?><input name="seocServerOwn" style="width:100px;" size="150"  id="seocServerOwn" tabindex="1" value="<?=get_option("seocServer");?>" type="text"> <br>
    </label>
    <?php _e('Patrón de otro servidor: google.xxx (google.nl, google.ch...)', 'seowatcher'); ?><br>
	<?php _e('Seleccione su servidor de Google favorito.', 'seowatcher'); ?></p>
	
	</div>
	</div>
	
	<input type="submit" class="button-primary" value="<?php _e('Guardar'); ?>" />
	<input name="action" value="insert" type="hidden" />
	</form>
	<br><br><br>
	
	<form name="form1" method="post" action="<?=$location ?>">
	<div id="keyworddiv" class="postbox" style="max-width:100%;""><h3 class="hndle"><span><?php _e('Actualizar ahora!', 'seowatcher'); ?></span></h3>

	<div class="inside">
	<?php _e('<p>Los datos de palabra clave se recomiendan actualizar manualmente, cada 24 horas. Si cambia las palabras clave o URL, deberá provocar una actualización inmediata.</p><p><b>Actualizar con demasiada frecuencia puede conducir a problemas!</b></p>', 'seowatcher'); ?>
	<input type="submit" class="button-primary" value="Actualizar" />
	<input name="action" value="update" type="hidden" />
	</div>
	</div>
	</form>

	
<div id="submitdiv" class="postbox" style="margin-top:89px;"><h3 class="hndle"><span><?php _e('Acerca de Seo Spy', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">
	<?php _e('Seo Spy le ayuda a realizar un seguimiento de su posicionamiento en Google.', 'seowatcher'); ?><br /><?php _e(' En el <b>Escritorio</b> encontrará las estadísticas diarias actuales y el botón para actualizar.', 'seowatcher'); ?>
	</div>
	</div>
	
	<div id="submitdiv" class="postbox"><h3 class="hndle"><span><?php _e('Donar!!', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">
	<?php _e('Mucho trabajo se ha puesto en el desarrollo de la Seo Spy.', 'seowatcher'); ?><br /><?php _e(' Si usted quiere asegurarse de que la Vigía Seo queda libre, por favor considere hacer una donación.', 'seowatcher'); ?><br>

	<br>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
	<input type="hidden" name="cmd" value="_s-xclick">
	<input type="hidden" name="hosted_button_id" value="KBRBCP9YD9EEL">
	<input type="image" src="https://www.paypalobjects.com/es_ES/ES/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal. La forma rápida y segura de pagar en Internet.">
	<img alt="" border="0" src="https://www.paypalobjects.com/es_ES/i/scr/pixel.gif" width="1" height="1">
	</form>
	</div>
	</div>

	<div id="submitdiv" class="postbox"><h3 class="hndle"><span><?php _e('Por qué puedo definir tan pocas palabras?', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">
	<?php _e('Esta limitación está puesta para su protección. Si hay demasiadas solicitudes de búsqueda enviadas a Google por una misma IP, puede ocurrir, que Google bloquee la IP por un tiempo. Por lo tanto, más vale prevenir que curar.', 'seowatcher'); ?>

	</div>
	</div>	
	
	<div id="submitdiv" class="postbox"><h3 class="hndle"><span><?php _e('Cuándo se actualizan los datos?', 'seowatcher'); ?></span></h3>
	<div class="inside" style="padding:5px;">

	<?php _e('La tarea de actualización manual se recomienda ejecutar todos los días a medianoche.', 'seowatcher'); ?>
	</div>
	</div>


	</div>
	</div>
	</div>
	<?php
} 

function seowatcher_dashboard_setup() {
	wp_add_dashboard_widget( 'seowatcher_option_page_setup', __( 'Seo Spy' ), 'seowatcher_dashboard_content' );
}

function seowatcher_option_page_setup() {
	#add_options_page('Seo Spy', 'Seo Spy', 9, __FILE__, 'seowatcher_option_page_content'); 
	#add_options_page('Seo Spy', 'Seo Spy', 'manage_options', 'wp-seospy.php', array(&$this, 'seowatcher_option_page_content'));
	add_menu_page(__('Seo Spy - Options', 'Seo Spy'), 'Seo Spy', '9', __FILE__, 'seowatcher_option_page_content', '/wp-content/plugins/wp-seo-spy-google/images/menu_icon.png');
}


/**
 * Hooks
 */
register_activation_hook( __FILE__, 'seowatcher_setup' );
register_deactivation_hook(__FILE__, 'seowatcher_uninstall');
//add_action('seoc_cronjob', 'seowatcher_update_keyworddata');
add_action('admin_menu', 'seowatcher_option_page_setup');
add_action('wp_dashboard_setup', 'seowatcher_dashboard_setup');
add_action('wp_footer', 'seowatcher_supportlink');
