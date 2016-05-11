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
        printf("<html><head><title>TEST PAGE</title></head>\n");
        printf("<body><pre>\n");
        printf("POST QUERY STRING: ");
        for(i = 0; i < length; i++) {
                a = getchar();
                putchar(a);
        }
        printf("</pre></body></html>");
        exit(0);
}
