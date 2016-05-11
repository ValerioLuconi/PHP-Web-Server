#include <stdlib.h>
#include <stdio.h>

int main(int argc, char** argv)
{
        char* env;
        int length, i;
        char a;
        env = getenv("CONTENT_LENGTH");
        length = atoi(env);
        printf("Content-type: text/html\r\n\r\n");
        printf("<html><head><title>PAGINA DI PROVA</title></head>\n");
        printf("<body><pre>\n");
        printf("La Query string tramite POST: ");
        for(i = 0; i < length; i++) {
                a = getchar();
                putchar(a);
        }
        printf("</pre></body></html>");
        exit(0);
}
