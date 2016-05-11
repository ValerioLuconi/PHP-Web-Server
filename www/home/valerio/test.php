<?php

echo "<html><head><title>PAGINA DI PROVA</title></head>\n";

echo "<body><pre>\n";
echo "Variabili di ambiente\n";
print_r($_ENV);

echo "Variabili Server:\n";
print_r($_SERVER);

if(isset($_GET)) {
        echo "Variabili GET:\n";
        print_r($_GET);
}
if(isset($_POST)) {
        echo "Variabili POST:\n";
        print_r($_POST);
}

echo "</pre></body></html>";

?>
