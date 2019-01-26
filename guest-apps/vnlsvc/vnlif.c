#include "vnlsvc.h"
#include <arpa/inet.h>

bool vnlif_parse(struct vnlif* result, char* input) {
  memset(result, 0, sizeof(*result));
  char p[160]; char* token;
  strncpy(p, input, 160);

  token = strtok(p, "/");
  if (token == NULL || token[0] == '\0' || strlen(token) >= IFNAMSIZ) return false;
  strncpy(result->ifname, token, IFNAMSIZ);

  token = strtok(NULL, "/");
  if (token == NULL || strlen(token) >= IFNAMSIZ) return false;
  strncpy(result->tapname, token, IFNAMSIZ);

  token = strtok(NULL, "/");
  if (token == NULL ||
    NULL == ether_aton_r(token, &(result->vmac))) return false;

  token = strtok(NULL, "#");
  if (token == NULL || 0 ==
    inet_aton(token, &(result->vip))) return false;
  token = strtok(NULL, "/");
  if (token == NULL || 0 ==
    inet_aton(token, &(result->vmask))) return false;

  result->tip.sin_family = AF_INET;
  token = strtok(NULL, ":");
  if (token == NULL || 0 ==
    inet_aton(token, &(result->tip.sin_addr))) return false;
  token = strtok(NULL, "/");
  if (token == NULL || 0 ==
    (result->tip.sin_port = htons((uint16_t)atoi(token)))) return false;

  result->rtip.sin_family = AF_INET;
  token = strtok(NULL, ":");
  if (token == NULL || 0 ==
    inet_aton(token, &(result->rtip.sin_addr))) return false;
  token = strtok(NULL, "/");
  if (token == NULL || 0 ==
    (result->rtip.sin_port = htons((uint16_t)atoi(token)))) return false;

  return true;
}

void iflist_hwinfo(c_hwinfo* result, struct vnlif* iflist, int ifcount) {
  result->mType = htonl(VNSHWINFO);
  int j = -1;
  for (int i = 0; i < ifcount; ++i) {
    result->mHWInfo[++j].mKey = htonl(HWINTERFACE);
    strncpy(result->mHWInfo[j].value, iflist[i].ifname, 32);
    result->mHWInfo[++j].mKey = htonl(HWETHER);
    memcpy(result->mHWInfo[j].value, &(iflist[i].vmac), sizeof(struct ether_addr));
    result->mHWInfo[++j].mKey = htonl(HWETHIP);
    memcpy(result->mHWInfo[j].value, &(iflist[i].vip), sizeof(struct in_addr));
    result->mHWInfo[++j].mKey = htonl(HWMASK);
    memcpy(result->mHWInfo[j].value, &(iflist[i].vmask), sizeof(struct in_addr));
  }
  result->mLen = htonl(2*sizeof(uint32_t) + (j+1)*sizeof(c_hw_entry));
}

int iflist_find(struct vnlif* iflist, int ifcount, char* ifname) {
  for (int i = 0; i < ifcount; ++i) {
    if (0 == strcmp(iflist[i].ifname, ifname)) {
      return i;
    }
  }
  return -1;
}
