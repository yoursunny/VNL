#include "vnlsvc.h"
#include <stdio.h>
#include <fcntl.h>
#include <time.h>
#include <poll.h>

int main(int argc, char* argv[]) {
  int opt;
  bool vns_console = false;
  char* command_file = NULL; char* pid_file = NULL;
  int i, ifcount = 0; struct vnlif iflist[vnlif_maxcount];

  int command_fd, command_eth = -1; FILE* pid_fd;
  struct pollfd pollfds[2+2*vnlif_maxcount]; int pollres;
  char buffer[MSGSIZE]; int len;
  char* pktbuffer = buffer + MSGHDRSIZE;
  c_base* vnsbasehdr = (c_base*)buffer;
  c_hwinfo* vnshwinfo = (c_hwinfo*)buffer;
  c_packet_header* vnspkthdr = (c_packet_header*)buffer;

// ---------------- parse command options ----------------
  while ((opt = getopt(argc, argv, "si:c:p:")) != -1) {
    switch (opt) {
    case 's': vns_console = true; break;
    case 'i':
      if (ifcount < vnlif_maxcount) {
        if (!vnlif_parse(iflist + ifcount, optarg)) die("vnlif_parse");
      } else {
        die("cmdline vnlif_maxcount");
      }
      ++ifcount;
      break;
    case 'c': command_file = optarg; break;
    case 'p': pid_file = optarg; break;
    }
  }

  if (ifcount == 0 || command_file == NULL || pid_file == NULL) {
    printf("vnlsvc: Virtual Network Lab Service\nTAP-UDP: vnlsvc -i eth0/tap0/1a:8e:22:b1:da:f1/198.51.100.1#255.255.255.0/192.0.2.1:2001/192.0.2.2:2001/ -c /tmp/command.pipe -p /tmp/vnltun.pid\nVNSconsole-UDP: vnlsvc -s -i eth0//1a:8e:22:b1:da:f1/198.51.100.1#255.255.255.0/192.0.2.1:2001/192.0.2.2:2001/ -c /tmp/command.pipe -p /tmp/vnltun.pid\n");
    exit(1);
  }

// ---------------- open files and sockets ----------------
  command_fd = open(command_file, O_RDONLY | O_NONBLOCK);
  if (command_fd < 0) die("command open");

  pollfds[0].fd = 0; pollfds[0].events = vns_console ? POLLIN : 0;
  pollfds[1].fd = command_fd; pollfds[1].events = POLLIN;
  for (i = 0; i < ifcount; ++i) {
    pollfds[2+i].fd = iflist[i].udp_fd
      = udp_open(&(iflist[i].tip), &(iflist[i].rtip));
    pollfds[2+i].events = POLLIN;
    if (!vns_console) {
      pollfds[2+ifcount+i].fd = iflist[i].tap_fd = tap_open(iflist[i].tapname);
      pollfds[2+ifcount+i].events = POLLIN;
    }
  }
  //pollfds: 0=stdin, 1=command pipe, 2..2+ifcount-1=UDP, 2+ifcount..2+2*ifcount-1=TAP

// ---------------- initialize ----------------
  pid_fd = fopen(pid_file, "w");
  if (pid_fd == NULL) die("pid output");
  fprintf(pid_fd, "%d", getpid());
  fclose(pid_fd);

  srand(time(NULL));

  if (vns_console) {
    con_init();
    vns_wAuthReq(buffer); con_write(buffer);
  }

// ---------------- main poll loop ----------------
  while (1) {
    pollres = poll(pollfds, 2+(vns_console?1:2)*ifcount, -1);
    if (pollres == -1) die("poll");
    else if (pollres == 0) { perror("poll timeout"); continue; }

/*
    for (i = 0; i < 2+(vns_console?1:2)*ifcount; ++i) {
      if (pollfds[i].revents & (POLLERR | POLLHUP | POLLNVAL)) {
        FILE* dbglog = fopen("/tmp/vnlsvc.debug.log","a");
        fprintf(dbglog,"%d %d %d %x\n",time(NULL),getpid(),i,pollfds[i].revents);
        fclose(dbglog);
      }
    }
*/

// ---------------- VNS protocol, console to UDP ----------------
    if (vns_console && (pollfds[0].revents & POLLIN)) {
      if (con_read(buffer)) {
        switch (vns_getType(buffer)) {
        case VNS_AUTH_REPLY:
          vns_wAuthStatus(buffer); con_write(buffer);
          break;
        case VNSOPEN:
          iflist_hwinfo(vnshwinfo, iflist, ifcount);
          con_write(buffer);
          break;
        case VNSPACKET:
          i = iflist_find(iflist, ifcount, vnspkthdr->mInterfaceName);
          if (i < 0) break;
          len = ntohl(vnspkthdr->mLen) - sizeof(c_packet_header);
          if (lossy_filter(iflist[i].lossy)) {
            udp_write(iflist[i].udp_fd, pktbuffer, len, &(iflist[i].rtip));
          }
          break;
        }
      }
    }
// ---------------- console error ----------------
    if (vns_console && (pollfds[0].revents & (POLLERR | POLLHUP))) {
      die("console error");
    }

// ---------------- setlossy command ----------------
    if (pollfds[1].revents & POLLIN) {
      if (command_eth < 0 && 1 == read(command_fd, buffer, 1)) {
        command_eth = buffer[0];
      }
      if (1 == read(command_fd, buffer, 1) && command_eth < ifcount) {
        iflist[command_eth].lossy = buffer[0];
      }
    }
    if (pollfds[1].revents & POLLHUP) {
      command_eth = -1;
      close(command_fd);
      pollfds[1].fd = command_fd = open(command_file, O_RDONLY | O_NONBLOCK);
      if (command_fd < 0) die("command reopen");
    }

    for (i = 0; i < ifcount; ++i) {
// ---------------- UDP to console/TAP ----------------
      if (pollfds[2+i].revents & POLLIN) {
        len = udp_read(iflist[i].udp_fd, pktbuffer, &(iflist[i].rtip));
        if (len > 0) {
          if (vns_console) {
            vns_wPacketHdr(buffer, len, iflist[i].ifname);
            con_write(buffer);
          } else {
            tap_write(iflist[i].tap_fd, pktbuffer, len);
          }
        }
      }
// ---------------- UDP error ----------------
      if (pollfds[2+i].revents & POLLERR) {
        int sockerror; socklen_t socklen = sizeof(int);
        getsockopt(iflist[i].udp_fd, SOL_SOCKET, SO_ERROR, &sockerror, &socklen);
      }
// ---------------- TAP to UDP ----------------
      if (!vns_console && (pollfds[2+ifcount+i].revents & POLLIN)) {
        len = tap_read(iflist[i].tap_fd, pktbuffer);
        if (len > 0 && lossy_filter(iflist[i].lossy)) {
          udp_write(iflist[i].udp_fd, pktbuffer, len, &(iflist[i].rtip));
        }
      }
    }
  }
}
