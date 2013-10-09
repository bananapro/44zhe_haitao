<?php

$url = 'http://www.amazon.com/?_encoding=UTF8&camp=1789&creative=9325&linkCode=ur2&tag=sbearsblog0d-20';
$jump_url = 'http://haitao.44zhe.com/redirect/' . urlencode(base64_encode($url));

echo "<b>url:</b>".$url;
echo "<br><br><br>";
echo "<b>jump url:</b>" .$jump_url;

?>
