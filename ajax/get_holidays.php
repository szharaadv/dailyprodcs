<?php
header('Content-Type: application/json');
// No company calendar table in this app yet — the date picker degrades
// gracefully to a plain calendar with nothing highlighted.
echo json_encode([]);
