<?php
$themes = json_decode(file_get_contents('../packages.json'));
echo json_encode($themes->themes->{$_GET['theme']});
