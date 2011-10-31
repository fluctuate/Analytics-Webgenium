<?php
/*------------------------------------------------------------------------
# com_analytics - Webgenium Analytics
# ------------------------------------------------------------------------
# author    Luiz Felipe Weber - Webgenium System
# copyright Copyright (C) 2011 webgenium.com.br. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://loja.weber.eti.br / http://webgenium.com.br
# Technical Support:  Forum - https://github.com/webgenium/Analytics-Webgenium
-------------------------------------------------------------------------*/
/*  Copyright 2009  Carson McDonald  (carson@ioncannon.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
session_start();
require(dirname(__FILE__) . '/ga-lib.php');
require(dirname(__FILE__) . '/gauth-lib.php');

/*

Plugin Name: Google Analytics Dashboard
Plugin URI: http://www.ioncannon.net/projects/google-analytics-dashboard-wordpress-widget/
Description: Google Analytics graph integration.
Version: 1.0.3
Author: Carson McDonald
Author URI: http://www.ioncannon.net/

*/

// =====================================================================
//
// Admin options area
//
// =====================================================================

function add_option($key,$value)
{
	$_SESSION[$key]=$value;

}


function get_option($key)
{
	return $_SESSION[$key];
}

function delete_option($key)
{
	unset($_SESSION[$key]);
}


function gad_ajax_set_preference()
{
  if( function_exists('current_user_can') && !current_user_can('manage_options') )
  {
    die("alert('Cheatin&#8217; uh?')");
  }

  switch($_POST['pi'])
  {
    case 'base-stats':
      delete_option('gad_bs_toggle');
      add_option('gad_bs_toggle', $_POST['pv']);
    break;
    case 'extended-stats':
      delete_option('gad_es_toggle');
      add_option('gad_es_toggle', $_POST['pv']);
    break;
    default:
      die("alert('Unknown option.')");
  }

  die("");
}



function gad_admin_plugin_options($info_message = '')
{
  if(!class_exists('SimpleXMLElement'))
  {
    echo '<br/><br/><div id="message" class="updated fade"><p><strong>It appears that <a href="http://us3.php.net/manual/en/book.simplexml.php">SimpleXML</a> is not compiled into your version of PHP. It is required for this plugin to function correctly.</strong></p></div>';
  }
  else
  {
    $gad_auth_token = get_option('gad_auth_token');

    if(isset($gad_auth_token) && $gad_auth_token != '')
    {
      gad_admin_handle_other_options($info_message);
    }
    else
    {
      gad_admin_handle_login_options($info_message);
    }
  }
}

function gad_admin_handle_other_options($info_message = '')
{
  if( isset($_POST['SubmitOptions']) )
  {
    if( function_exists('current_user_can') && !current_user_can('manage_options') )
    {
      die(__('Cheatin&#8217; uh?'));
    }

    if( isset($_POST['ga_forget_pass']) )
    {
      delete_option('gad_login_pass', $_POST['ga_pass']);
    }

    delete_option('gad_account_id');
    add_option('gad_account_id', $_POST['ga_account_id']);

    if( isset($_POST['ga_forget_auth']) )
    {
      delete_option('gad_auth_token');
      gad_admin_plugin_options('Auth Reset');
      return;
    }

  }

  $ga = new GALib(get_option('gad_auth_token'));
  $account_hash = $ga->account_query();


  if($ga->isError())
  {
    if($ga->isAuthError())
    {
      delete_option('gad_auth_token');
      gad_admin_plugin_options();
      return;
    }
    else
    {
      echo 'Error gathering analytics data from Google: ' . strip_tags($ga->getErrorMessage());
      return;
    }
  }

?>

  <div class="wrap" style="padding-top: 50px;">

<?php if( isset($info_message) && trim($info_message) != '' ) : ?>
    <div id="message" class="updated fade"><p><strong><?php echo $info_message ?></strong></p></div>
<?php endif; ?>

<?php if( isset($error_message) ) : ?>
    <div id="message" class="error fade"><p><strong><?php echo $error_message ?></strong></p></div>
<?php endif; ?>

    <form action="" method="post">


<?php
    if(sizeof($account_hash) == 0)
    {
      echo '<span id="ga_account_id">No accounts available.</span>';
    }
    else
    {
    	$current_account_id = isset($_POST['ga_account_id']) ? $_POST['ga_account_id'] : get_option('gad_account_id') !== false ? get_option('gad_account_id') : '';
		if($current_account_id)
		{

			$content = Array ("pageviews","pageviews","pageviews","pageviews");
			dashboard_gad();
			echo "<br/> Select other Account";
		}
		else
		{
		?>
		<table class="form-table">
		        <tr valign="top">
		          <th scope="row"><label for="ga_account_id">Available Accounts</label></th>
          <td>
		<?php
		echo '<select id="ga_account_id" name="ga_account_id">';
		  foreach($account_hash as $account_id => $account_name)
		  {
			echo '<option value="' . $account_id . '" ' . ($current_account_id == $account_id ? 'selected' : '') . '>' . $account_name . '</option>';
		  }
		  echo '</select>';
		}
    }
?>

<?php if( !isset($current_account_id) || $current_account_id == '' ) : ?>
    <div style="padding-top: 2px; padding-left: 5px;">
      <b>Note:</b> You will need to select an account and save before the analytics dashboard will work.
    </div>
<?php endif; ?>
          </td>
        </tr>

      </table>

      <p class="submit">
        <input type="submit" name="SubmitOptions" class="button-primary" value="Submit" />
      </p>

    </form>

  </div>

<?php
}

function gad_admin_handle_login_options($info_message = '')
{

  if( isset($_POST['SubmitLogin']) )
  {
    if( function_exists('current_user_can') && !current_user_can('manage_options') )
    {
      die(__('Cheatin&#8217; uh?'));
    }

    if( !isset($_POST['ga_email']) || trim($_POST['ga_email']) == '' )
    {
      $error_message = "Email is required";
    }
    else if( !isset($_POST['ga_pass']) || $_POST['ga_pass'] == '' )
    {
      $error_message = "Password is required";
    }
    else
    {
      add_option('gad_login_email', $_POST['ga_email']);

      if(isset($_POST['ga_save_pass']))
      {
        add_option('gad_login_pass', $_POST['ga_pass']);
      }
      else
      {
        delete_option('gad_login_pass', $_POST['ga_pass']);
      }

      $gauth = new GAuthLib('wpga-display-1.0');
      if(isset($_POST['ga_captcha_token']) && isset($_POST['ga_captcha']))
      {
        $gauth->authenticate($_POST['ga_email'], $_POST['ga_pass'], 'analytics', $_POST['ga_captcha_token'], $_POST['ga_captcha']);
      }
      else
      {
        $gauth->authenticate($_POST['ga_email'], $_POST['ga_pass'], 'analytics');
      }

      if($gauth->isError())
      {
        $error_message = $gauth->getErrorMessage();
      }
      else
      {
        add_option('gad_auth_token', $gauth->getAuthToken());
        gad_admin_plugin_options('Login successful.');
        return;
      }
    }
  }

?>

  <div class="wrap" style="padding-top: 50px;">

<?php if( isset($info_message) && trim($info_message) != '' ) : ?>
    <div id="message" class="updated fade"><p><strong><?php echo $info_message ?></strong></p></div>
<?php endif; ?>

<?php if( isset($error_message) ) : ?>
    <div id="message" class="error fade"><p><strong><?php echo $error_message ?></strong></p></div>
<?php endif; ?>

    <form action="" method="post">

      <table class="form-table">

        <tr valign="top">
          <th scope="row"><label for="ga_email">Google Analytics Email</label></th>
          <td><input name="ga_email" type="text" size="15" id="ga_email" class="regular-text" value="" /></td>
        </tr>

        <tr valign="top">
          <th scope="row"><label for="ga_pass">Google Analytics Password</label></th>
          <td><input name="ga_pass" type="password" size="15" id="ga_pass" class="regular-text" value="" /></td>
        </tr>

<?php if( isset($gauth) && $gauth->requiresCaptcha() ) : ?>
        <tr valign="top">
          <th scope="row"><label for="ga_captcha">Google CAPTCHA</label></th>
          <td>
            <img src="<?php echo $gauth->getCaptchaImageURL(); ?>"/><br/><br/>
            <input name="ga_captcha" type="text" size="10" id="ga_captcha" class="regular-text" value="" />
            <input type="hidden" name="ga_captcha_token" value="<?php echo $gauth->getCaptchaToken(); ?>"/>
          </td>
        </tr>
<?php endif; ?>

      </table>

      <p class="submit">
        <input type="submit" name="SubmitLogin" class="button-primary" value="Submit" />
      </p>
    </form>

  </div>

<?php
}

// =====================================================================
//
// Tag area
//
// =====================================================================

function gad_content_tag_filter( $content )
{
  return preg_replace_callback('/\[\s*(pageviews)(:(.*))?\s*\]/iU', gad_content_tag_filter_replace, $content);
}

function gad_content_tag_filter_replace($matches)
{
  $link_uri = substr($_SERVER["REQUEST_URI"], -20);
echo "inside func";
var_dump($matches);
  switch(strtolower($matches[1]))
  {
    case 'pageviews':
      $ga = new GALib(get_option('gad_auth_token'), get_option('gad_account_id'));

      $start_date = date('Y-m-d', time() - (60 * 60 * 24 * 30));
      $end_date = date('Y-m-d');
      echo "niside pageviews";

      if(isset($matches[3]) && trim($matches[3]) != '')
      {
        $data = $ga->daily_uri_pageviews_for_date_period($link_uri, $start_date, $end_date);
        $error_type = gad_request_error_type($ga);
        if($error_type == 'perm') return '';
        else if($error_type == 'retry') $data = $ga->daily_uri_pageviews_for_date_period($link_uri, $start_date, $end_date);

        $minvalue = 999999999;
        $maxvalue = 0;
        $count = 0;
        var_dump($data);

        foreach($data as $date => $value)
        {
          if($minvalue > $value['ga:pageviews'])
          {
            $minvalue = $value['ga:pageviews'];
          }
          if($maxvalue < $value['ga:pageviews'])
          {
            $maxvalue = $value['ga:pageviews'];
          }
          $cvals .= $value['ga:pageviews'] . ($count < sizeof($data)-1 ? "," : "");
          $count++;
        }

        return '<img width="90" height="30" src="http://chart.apis.google.com/chart?chs=90x30&cht=ls&chf=bg,s,FFFFFF00&chco=0077CC&chd=t:' . $cvals . '&chds=' . $minvalue . ',' . $maxvalue . '"/>';
      }
      else
      {
        $data = $ga->total_uri_pageviews_for_date_period($link_uri, $start_date, $end_date);
        $error_type = gad_request_error_type($ga);
        if($error_type == 'perm') return '';
        else if($error_type == 'retry') $data = $ga->total_uri_pageviews_for_date_period($link_uri, $start_date, $end_date);

        return $data['value'];
      }
    break;
    default:
      return '';
  }
}


// =====================================================================
//
// Posts area
//
// =====================================================================

function gad_post_columns($defaults)
{
  $defaults['analytics'] = __('Analytics');
  return $defaults;
}

function gad_request_error_type($ga)
{
  if($ga->isError())
  {
    if($ga->isAuthError())
    {
      if(get_option('gad_login_pass') === false || get_option('gad_login_email') === false)
      {
        echo 'You need to log in and select an account in the <a href="options-general.php?page=google-analytics-dashboard/google-analytics-dashboard.php">options panel</a>.';
        return 'perm';
      }
      else
      {
        $gauth = new GAuthLib('wpga-display-1.0');
        $gauth->authenticate(get_option('gad_login_email'), get_option('gad_login_pass'), 'analytics');

        if($gauth->isError())
        {
          $error_message = $gauth->getErrorMessage();
          echo 'You need to log in and select an account in the <a href="options-general.php?page=google-analytics-dashboard/google-analytics-dashboard.php">options panel</a>.';
          return 'perm';
        }
        else
        {
          delete_option('gad_auth_token');
          add_option('gad_auth_token', $gauth->getAuthToken());
          $ga->setAuth($gauth->getAuthToken());
          return 'retry';
        }
      }
    }
    else
    {
      echo 'Error gathering analytics data from Google: ' . strip_tags($ga->getErrorMessage());
      return 'perm';
    }
  }
  else
  {
    return 'none';
  }
}

//add_action('manage_posts_custom_column', 'gad_post_custom_column', 10, 2);
function gad_post_custom_column($column_name, $post_id)
{
  global $wpdb;

  if(get_option('gad_auth_token') === false || get_option('gad_account_id') === false)
  {
    echo 'You need to log in and select an account in the <a href="options-general.php?page=google-analytics-dashboard/google-analytics-dashboard.php">options panel</a>.';
    return;
  }

  if( $column_name == 'analytics' )
  {
    $ga = new GALib(get_option('gad_auth_token'), get_option('gad_account_id'));

    $link_value = get_permalink($post_id);
    $url_data = parse_url($link_value);
    $link_uri = substr($url_data['path'] . (isset($url_data['query']) ? ('?' . $url_data['query']) : ''), -20);

    $is_draft = $wpdb->get_var("SELECT count(1) FROM $wpdb->posts WHERE post_status = 'draft' AND ID = $post_id");
    if($link_uri == '' || (isset($is_draft) && $is_draft > 0))
    {
      echo "";
    }
    else
    {
      $start_date = date('Y-m-d', time() - (60 * 60 * 24 * 30));
      $end_date = date('Y-m-d');

      $data = $ga->summary_by_partial_uri_for_date_period($link_uri, $start_date, $end_date);
      $error_type = gad_request_error_type($ga);
      if($error_type == 'perm') return;
      else if($error_type == 'retry') $data = $ga->summary_by_partial_uri_for_date_period($link_uri, $start_date, $end_date);

      $minvalue = 999999999;
      $maxvalue = 0;
      $pageviews = 0;
      $exits = 0;
      $uniques = 0;
      $count = 0;
      foreach($data as $date => $value)
      {
        if($minvalue > $value['ga:pageviews'])
        {
          $minvalue = $value['ga:pageviews'];
        }
        if($maxvalue < $value['ga:pageviews'])
        {
          $maxvalue = $value['ga:pageviews'];
        }
        $cvals .= $value['ga:pageviews'] . ($count < sizeof($data)-1 ? "," : "");
        $count++;

        $pageviews += $value['ga:pageviews'];
        $exits += $value['ga:exits'];
        $uniques += $value['ga:uniquePageviews'];
      }

?>
    <table style="padding:0">
      <tr>
        <td style="border:0">
          <img width="90" height="30" src="http://chart.apis.google.com/chart?chs=90x30&cht=ls&chf=bg,s,FFFFFF00&chco=0077CC&chd=t:<?php echo $cvals; ?>&chds=<?php echo $minvalue; ?>,<?php echo $maxvalue; ?>"/>
        </td>
        <td style="border:0; padding:0">
          <?php echo number_format($pageviews); ?> pageviews<br/>
          <?php echo number_format($exits); ?> exits<br/>
          <?php echo number_format($uniques); ?> uniques<br/>
        </td>
      </tr>
    </table>
<?php
    }
  }
}

// =====================================================================
//
// Dashboard widget area
//
// =====================================================================



function dashboard_gad()
{
  if(get_option('gad_auth_token') === false || get_option('gad_account_id') === false)
  {
    echo 'You need to log in and select an account in the <a href="options-general.php?page=google-analytics-dashboard/google-analytics-dashboard.php">options panel</a>.';
    return;
  }

  $start_date_ts = time() - (60 * 60 * 24 * 30); // 30 days in the past
  $start_date = date('Y-m-d', $start_date_ts);
  $end_date = date('Y-m-d');

  $ga = new GALib(get_option('gad_auth_token'), get_option('gad_account_id'));

  $summary_data = $ga->summary_for_date_period($start_date, $end_date);
  $error_type = gad_request_error_type($ga);
  if($error_type == 'perm') return;
  else if($error_type == 'retry') $summary_data = $ga->summary_for_date_period($start_date, $end_date);
  $daily_pageviews = $ga->daily_pageviews_for_date_period($start_date, $end_date);
  $error_type = gad_request_error_type($ga);
  if($error_type == 'perm') return;
  else if($error_type == 'retry') $daily_pageviews = $ga->daily_pageviews_for_date_period($start_date, $end_date);
  $pages = $ga->pages_for_date_period($start_date, $end_date);
  $error_type = gad_request_error_type($ga);
  if($error_type == 'perm') return;
  else if($error_type == 'retry') $pages = $ga->pages_for_date_period($start_date, $end_date);
  $keywords = $ga->keywords_for_date_period($start_date, $end_date);
  $error_type = gad_request_error_type($ga);
  if($error_type == 'perm') return;
  else if($error_type == 'retry') $keywords = $ga->keywords_for_date_period($start_date, $end_date);
  $sources = $ga->sources_for_date_period($start_date, $end_date);
  $error_type = gad_request_error_type($ga);
  if($error_type == 'perm') return;
  else if($error_type == 'retry') $sources = $ga->sources_for_date_period($start_date, $end_date);

  $labelv = '';
  $labelp = '';
  $minvalue = 999999999;
  $maxvalue = 0;
  $count = 0;
  $total_count = sizeof($daily_pageviews);
  $total_pageviews = 0;
  $first_monday_index = -1;
  foreach($daily_pageviews as $pageview)
  {
    $current_date = $start_date_ts + (60 * 60 * 24 * $count);
    $day = date('w', $current_date); // 0 = sun 6 = sat

    if( $day == 1 ) // monday
    {
      if( $first_monday_index == -1 )
      {
        $first_monday_index = $count;
      }
      $labelv .= '|' . urlencode(date('D m/d', $current_date));
      $labelp .= round($count/($total_count-1)*100, 2) . ',';
    }

    if($minvalue > $pageview) $minvalue = $pageview;
    if($maxvalue < $pageview) $maxvalue = $pageview;

    $cvals .= $pageview . ($count < $total_count-1 ? "," : "");
    $count++;
    $total_pageviews += $pageview;
  }

  $labelp = substr($labelp, 0, strlen($labelp)-1); // strip off the last ,

  $bs_toggle_option = get_option('gad_bs_toggle');
  $bs_toggle_option = !isset($bs_toggle_option) || $bs_toggle_option == '' ? 'hide' : $bs_toggle_option;

  $es_toggle_option = get_option('gad_es_toggle');
  $es_toggle_option = !isset($es_toggle_option) || $es_toggle_option == '' ? 'hide' : $es_toggle_option;
?>

<!--[if IE]><style>
.ie_layout {
  height: 0;
  he\ight: auto;
  zoom: 1;
}
</style><![endif]-->

  <div style="text-align: center;">

  <div style="padding-bottom: 5px;">
    <?php echo $start_date ?> to <?php echo $end_date ?> <br/>
    <img width="450" height="200" src="http://chart.apis.google.com/chart?chs=450x200&chf=bg,s,FFFFFF00&cht=lc&chco=0077CC&chd=t:<?php echo $cvals; ?>&chds=<?php echo ($minvalue - 20); ?>,<?php echo ($maxvalue + 20); ?>&chxt=x,y&chxl=0:<?php echo $labelv; ?>&chxr=1,<?php echo $minvalue; ?>,<?php echo $maxvalue; ?>&chxp=0,<?php echo $labelp; ?>&chm=V,707070,0,<?php echo $first_monday_index; ?>:<?php echo $total_count; ?>:7,1|o,0077CC,0,-1.0,6"/>
  </div>

  <div style="position: relative; padding-top: 5px;" class="ie_layout">
    <h4 style="position: absolute; top: 6px; left: 10px; background-color: #fff; padding-left: 5px; padding-right: 5px;">Base Stats <a id="toggle-base-stats" href="#">(<?php echo $bs_toggle_option; ?>)</a></h4>
    <hr style="border: solid #eee 1px"/><br/>
  </div>

  <div>
    <div id="base-stats" <?php if($bs_toggle_option == 'show') echo 'style="display: none"'; ?>>
    <div style="text-align: left;">
      <div style="width: 50%; float: left;">
        <table>
          <tr><td align="right"><?php echo number_format($summary_data['value']['ga:visits']); ?></td><td></td><td>Visits</td></tr>
          <tr><td align="right"><?php echo number_format($total_pageviews); ?></td><td></td><td>Pageviews</td></tr>
          <tr><td align="right"><?php echo (isset($summary_data['value']['ga:visits']) && $summary_data['value']['ga:visits'] > 0) ? round($total_pageviews / $summary_data['value']['ga:visits'], 2) : '0'; ?></td><td></td><td>Pages/Visit</td></tr>
        </table>
      </div>
      <div style="width: 50%; float: right;">
        <table>
          <tr><td align="right"><?php echo (isset($summary_data['value']['ga:entrances']) && $summary_data['value']['ga:entrances'] > 0) ? round($summary_data['value']['ga:bounces'] / $summary_data['value']['ga:entrances'] * 100, 2) : '0'; ?>%</td><td></td><td>Bounce Rate</td></tr>
          <tr><td align="right"><?php echo (isset($summary_data['value']['ga:visits']) && $summary_data['value']['ga:visits']) ? gad_convert_seconds_to_time($summary_data['value']['ga:timeOnSite'] / $summary_data['value']['ga:visits']) : '00:00:00'; ?></td><td></td><td>Avg. Time on Site</td></tr>
          <tr><td align="right"><?php echo (isset($summary_data['value']['ga:visits']) && $summary_data['value']['ga:visits'] > 0) ? round($summary_data['value']['ga:newVisits'] / $summary_data['value']['ga:visits'] * 100, 2) : '0'; ?>%</td><td></td><td>% New Visits</td></tr>
        </table>
      </div>
      <br style="clear: both"/>
    </div>
    </div>

  </div>

  <div style="position: relative; padding-top: 5px;" class="ie_layout">
    <h4 style="position: absolute; top: 6px; left: 10px; background-color: #fff; padding-left: 5px; padding-right: 5px;">Extended Stats <a id="toggle-extended-stats" href="#">(<?php echo $es_toggle_option; ?>)</a></h4>
    <hr style="border: solid #eee 1px"/><br/>
  </div>

  <div>
    <div id="extended-stats" <?php if($es_toggle_option == 'show') echo 'style="display: none"'; ?>>
      <div style="text-align: left; font-size: 90%;">
        <div style="width: 50%; float: left;">

          <h4 class="heading"><?php echo 'Top Posts'; ?></h4>

          <div style="padding-top: 5px;">
<?php
  $z = 0;
  foreach($pages as $page)
  {
    $url = $page['value'];
    $title = $page['children']['value'];
    $page_views = $page['children']['children']['ga:pageviews'];
    echo '<a href="' . $url . '">' . $title . '</a><br/> <div style="color: #666; padding-left: 5px; padding-bottom: 5px; padding-top: 2px;">' . $page_views . ' views</div>';
    $z++;
    if($z > 10) break;
  }
?>
          </div>
        </div>

        <div style="width: 50%; float: right;">
          <h4 class="heading"><?php echo 'Top Searches' ; ?></h4>

          <div style="padding-top: 5px; padding-bottom: 15px;">
            <table width="100%">
<?php
  $z = 0;
  foreach($keywords as $keyword => $count)
  {
    if($keyword != "(not set)")
    {
      echo '<tr>';
      echo '<td>' . $count . '</td><td>&nbsp;</td><td> ' . $keyword . '</td>';
      echo '</tr>';
      $z++;
    }
    if($z > 10) break;
  }
?>
            </table>
          </div>

          <h4 class="heading"><?php echo 'Top Referers'; ?></h4>

          <div style="padding-top: 5px;">
            <table width="100%">
<?php
  $z = 0;
  foreach($sources as $source => $count)
  {
    echo '<tr>';
    echo '<td>' . $count . '</td><td>&nbsp;</td><td> ' . $source . '</td>';
    echo '</tr>';
    $z++;
    if($z > 10) break;
  }
?>
            </table>
          </div>
        </div>
        <br style="clear: both"/>
      </div>
    </div>

  </div>

  </div>

<?php
}

/**
 * Takes a time in seconds and turns it into a string with the format
 * of hours:minutes:seconds
 *
 * @return string in the format hours:minutes:seconds
 */
function gad_convert_seconds_to_time($time_in_seconds)
{
  $hours = floor($time_in_seconds / (60 * 60));
  $minutes = floor(($time_in_seconds - ($hours * 60 * 60)) / 60);
  $seconds = $time_in_seconds - ($minutes * 60) - ($hours * 60 * 60);

  return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

gad_admin_plugin_options();

?>
