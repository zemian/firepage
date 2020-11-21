<?php
global $app, $page;
// Return true JSON file payload
// NOTE: Ensure the file_content is not been transformed.
header('Content-Type: application/json');
echo $page->file_content;
