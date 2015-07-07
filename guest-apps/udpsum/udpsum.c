/*
UDP SUM service
compile:  gcc -o udpsum --std=c99 udpsum.c
execute:  ./udpsum server-port
interact: nc -p client-port server-ip server-port
          type some numbers separated by " +," and send as UDP packet
          server responds the sum of these numbers
*/
#include <stdint.h>
#include <unistd.h>
#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <netinet/ip.h>
#include <arpa/inet.h>
#include <poll.h>
#include <fcntl.h>

int main(int argc, char* argv[])
{
  if (argc != 2) { fprintf(stderr, "Usage: udpsum port\n"); exit(1); }
  int port = atoi(argv[1]);
  if (port < 1024) { fprintf(stderr, "port must be larger than 1024\n"); exit(1); }

  struct sockaddr_in sa;
  sa.sin_family = AF_INET;
  sa.sin_port = htons(port);
  sa.sin_addr.s_addr = INADDR_ANY;

  int fd = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
  if (fd < 0) { perror("socket"); exit(1); }
  if (bind(fd, (struct sockaddr*)&sa, sizeof(sa)) != 0) { perror("bind"); exit(1); }
  int opt_ip_pktinfo = 1;
  if (setsockopt(fd, IPPROTO_IP, IP_PKTINFO, &opt_ip_pktinfo, sizeof(opt_ip_pktinfo)) != 0) { perror("setsockopt"); exit(1); }

  struct pollfd pollfds[1];
  pollfds[0].fd = fd; pollfds[0].events = POLLIN;

  while (1) {
    int pollres = poll(pollfds, 1, -1);
    if (pollres == -1) { perror("poll"); exit(1); }

    if (pollfds[0].revents & POLLERR) {
      int sockerror; socklen_t socklen = sizeof(int);
      getsockopt(fd, SOL_SOCKET, SO_ERROR, &sockerror, &socklen);
    }
    if (pollfds[0].revents & POLLIN) {
      struct sockaddr_in peeraddr;
      char buffer[2000];
      struct iovec iov[1];
      iov[0].iov_base = buffer;
      iov[0].iov_len = sizeof(buffer) - 1;
      char cmbuf[0x100];
      struct in_pktinfo* pi;
      struct msghdr mh = {
        .msg_name = &peeraddr,
        .msg_namelen = sizeof(peeraddr),
        .msg_iov = iov,
        .msg_iovlen = 1,
        .msg_control = cmbuf,
        .msg_controllen = sizeof(cmbuf),
      };
      ssize_t nrecv = recvmsg(fd, &mh, MSG_DONTWAIT);
      if (nrecv == -1) {
        perror("recvmsg");
      } else {
        for (struct cmsghdr *cmsg = CMSG_FIRSTHDR(&mh); cmsg != NULL; cmsg = CMSG_NXTHDR(&mh, cmsg)) {
          if (cmsg->cmsg_level != IPPROTO_IP || cmsg->cmsg_type != IP_PKTINFO) continue;
          pi = (struct in_pktinfo*)CMSG_DATA(cmsg);
          break;
        }
        buffer[nrecv] = '\0';
        int sum = 0;
        char* token = strtok(buffer, " +,");
        while (token != NULL) {
          sum += atoi(token);
          token = strtok(NULL, " +,");
        }
        iov[0].iov_len = snprintf(buffer, sizeof(buffer), "%d\n", sum);
        sendmsg(fd, &mh, 0);
      }
    }
  }
}
