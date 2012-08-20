<?php

function sso_logout_link($text) {
	
	echo '<a href="?ssologout&origin=' . urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . '" title="Logout" class="bold">' . $text . '</a>';
}