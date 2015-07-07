#include "vnlsvc.h"
#include <netinet/ip.h>
#include <arpa/inet.h>


int udp_open(struct sockaddr_in* localSA, struct sockaddr_in* remoteSA) {
  int fd = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
  if (fd < 0) die("udp_open socket");
  if ((remoteSA->sin_addr.s_addr & htobe32(0xF0000000)) == htobe32(0xE0000000)) {//multicast
    uint32_t yes = 1;
    if (setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, &yes, sizeof(yes)) != 0) die("udp_open setsockopt SO_REUSEADDR");

    struct sockaddr_in localAny;
    localAny.sin_family = localSA->sin_family;
    localAny.sin_port = localSA->sin_port;
    localAny.sin_addr.s_addr = INADDR_ANY;
    if (bind(fd, (struct sockaddr*)&localAny, sizeof(struct sockaddr_in)) != 0) die("udp_open bind");

    uint32_t no = 0;
    if (setsockopt(fd, IPPROTO_IP, IP_MULTICAST_LOOP, &no, sizeof(no)) != 0) die("udp_open setsockopt IP_MULTICAST_LOOP");

    struct ip_mreqn mreq;
    mreq.imr_multiaddr.s_addr = remoteSA->sin_addr.s_addr;
    mreq.imr_address.s_addr = localSA->sin_addr.s_addr;
    mreq.imr_ifindex = 0;
    if (setsockopt(fd, IPPROTO_IP, IP_ADD_MEMBERSHIP, &mreq, sizeof(mreq)) != 0) die("udp_open setsockopt IP_ADD_MEMBERSHIP");
  } else {//unicast
    if (bind(fd, (struct sockaddr*)localSA, sizeof(struct sockaddr_in)) != 0) die("udp_open bind");
  }
  return fd;
}

int udp_read(int fd, char* buffer, struct sockaddr_in* remoteSA) {
  int nread = read(fd, buffer, FRAMESIZE);
  if (nread < 0) die("udp_read");
  return nread;
}

bool udp_write(int fd, char* buffer, int len, struct sockaddr_in* remoteSA) {
  if (len == 0) return false;
  int nwrite = sendto(fd, buffer, len, 0, (struct sockaddr*)remoteSA, sizeof(struct sockaddr_in));
  if (nwrite != len) perror("udp_write");
  return (nwrite == len);
}
