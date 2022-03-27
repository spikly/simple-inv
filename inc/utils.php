<?php

function escapeHtml($string, $flag = ENT_QUOTES)
{
	$safeText = htmlspecialchars($string, $flag);
	return $safeText;
}

function slugify($string)
{
	$string = strtolower($string);
	$string = trim($string);
	$string = str_replace(' ', '-', $string);
	return $string;
}