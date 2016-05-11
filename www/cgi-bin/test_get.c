#include <stdlib.h>
#include <stdio.h>

int main(int argc, char** argv)
{
        char* env;
        env = getenv("QUERY_STRING");
        printf("Content-type: text/html\r\n\r\n");
        printf("<html><head><title>PAGINA DI PROVA</title></head>\n");
        printf("<body><pre>\n");
        printf("La Query string tramite GET: %s\n", env);
        printf("</pre></body></html>");
        exit(0);
}
