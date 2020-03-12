<?php
/**
 * @package WP_StatusPage_io_plugin
 * @version 0.0.1
 */
/*
Plugin Name: WP StatusPage.io plugin
Version: 0.0.1
*/

// Props to https://digwp.com/2019/07/better-inline-script/ for making it easy to add inline scripts.
// Props to https://blog.grio.com/2012/11/a-copypaste-ble-jquery-notification-bar.html for the body prepend slideDown example.


// enqueue scripts
function wp_statuspageio_plugin_enqueue_scripts() {
	
	wp_enqueue_script('wp_statuspageio_plugin_script', get_template_directory_uri() .'/js/script.js', array(), '1.0', true);
	
	wp_statuspageio_plugin_inline_script();
	
}
add_action('wp_enqueue_scripts', 'wp_statuspageio_plugin_enqueue_scripts');


// inline scripts WP >= 4.5
function wp_statuspageio_plugin_inline_script() {

    $status_page = 'https://www.redditstatus.com';
    $product_name = 'Reddit';

	$wp_version = get_bloginfo('version');
	
	if (version_compare($wp_version, '4.5', '>=')) {
        $url = '/wp-json/wp_statuspage_io/v1/status';

        $script = '
        function showNotificationBar(message, duration, bgColor, txtColor, height) { 
            // set default values
            bgColor = typeof bgColor !== \'undefined\' ? bgColor : "green";
            txtColor = typeof txtColor !== "undefined" ? txtColor : "white";
            height = typeof height !== "undefined" ? height : 40;

            if ($("#notification-bar").size() == 0) {
                var HTMLmessage = "<div class=\'notification-message\' style=\'text-align:center; line-height: " + height + "px; color: " + txtColor + "; background-color: " + bgColor + ";\'> " + message + " </div>";
                $(\'body\').prepend("<div id=\'notification-bar\' style=\'display:none; width:100%; height:" + height + "px; background-color: " + bgColor + "; position: fixed; z-index: 100; color: " + txtColor + ";border-bottom: 1px solid " + txtColor + ";\'>" + HTMLmessage + "</div>");
            }
            /*animate the bar*/
            $(\'#notification-bar\').slideDown(function() {
                $("#notification-bar").css("display", "contents");
            });
        }
        
        var xmlhttp = new XMLHttpRequest();
        var url = "/wp-json/wp_statuspage_io/v1/status";

        xmlhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                var myArr = JSON.parse(this.responseText);
                wp_statuspage_io_callback(myArr);
            }
        };
        xmlhttp.open("GET", url, true);
        xmlhttp.send();

        function wp_statuspage_io_callback(arr) {
            if (arr.incidents.length > 0) {
                showNotificationBar("' . $product_name . ' has an ongoing incident. <a href=\'https://' . $status_page . '\'>Check status</a>.");
            }
            else if (arr.scheduled_maintenances.length > 0) {
                showNotificationBar("' . $product_name . ' has upcoming maintenance. <a href=\'https://' . $status_page . '\'>Check schedule</a>.");
            }
        }
        ';

		wp_add_inline_script('wp_statuspageio_plugin_script', $script, 'before');
		
	}

}

add_action( 'rest_api_init', function () {
    register_rest_route( 'wp_statuspage_io/v1', '/status', array(
      'methods' => 'GET',
      'callback' => 'wp_statuspage_io_get_status'
    ) );
  } );

function wp_statuspage_io_get_status( $request ) {
    $status_page = 'https://www.redditstatus.com';

    $cache_slug = 'wpstatuspageio_' . $status_page;
    $seconds_to_cache = 60;
    $url = $status_page . '/api/v2/summary.json';

    $status = get_transient($cache_slug);

    if (false === $status) {
        $response = file_get_contents($url);
        $status = json_decode($response);
        //echo 'I had to hit the web api';
        set_transient($cache_slug, $status, $seconds_to_cache);
    }

    $incidents = $status->incidents;

    $scheduled_maintenances = $status->scheduled_maintenances;

    $result = (object) [
        'incidents' =>         $incidents,
        'scheduled_maintenances' => $scheduled_maintenances
    ];

    return json_response($result);
}

function json_response($data=null, $httpStatus=200) {
    header_remove();

    header("Content-Type: application/json");

    header('Status: ' . $httpStatus);

    echo json_encode($data);

    exit();
}