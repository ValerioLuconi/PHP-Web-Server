<?php

echo "<html><head><title>TEST PAGE</title></head>\n";

echo "<body><pre>\n";
echo "Environment variables\n";
print_r($_ENV);

echo "Server vars:\n";
print_r($_SERVER);

if(isset($_GET)) {
        echo "GET vars:\n";
        print_r($_GET);
}
if(isset($_POST)) {
        echo "POST vars:\n";
        print_r($_POST);
}

echo "</pre></body></html>";

?>
