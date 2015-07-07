#include "vnlsvc.h"

void die(char* msg) {
  perror(msg);
  exit(1);
}

bool lossy_filter(int lossy) {
  return rand() % 100 >= lossy;
}
