<?php
//Functions
function sso_logout_link($text) {
	
	echo '<a href="?ssologout&origin=' . urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . '">' . $text . '</a>';
}