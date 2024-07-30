<div class="wrapper">

<?php

echo "<h1 class='awca_plugin_titles'>" . esc_html__('سفارشات انار', 'anar-360') . "</h1>";

$api_url = 'https://api.anar360.com/api/360/orders';
$token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6InF1ZXVlIiwiYWNjb3VudCI6IjY1YmQ3NTUxNDY4NDIyZTc4ZGUyOGRkOCIsInJvbGVzIjpbXSwiaWF0IjoxNzExNTY0MzE0fQ.WJoJWi08HdLxBZ20R-Kdt18whvKnCVqWQq7wl-3dEOk'; // Replace this with your actual Bearer token

awca_get_data_from_api($api_url, $token);

?>
</div>