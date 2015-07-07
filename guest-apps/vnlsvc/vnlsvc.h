#include <stdint.h>
#include <unistd.h>
#include <stdlib.h>
#include <string.h>
#include <stdio.h>
#include <net/if.h>
#include <netinet/ether.h>
#include <netinet/ip.h>
#include "vnscommand.h"

typedef int bool;
#define true -1
#define false 0

#define FRAMESIZE 1514
#define MSGHDRSIZE (sizeof(c_packet_header))
#define MSGSIZE (FRAMESIZE+MSGHDRSIZE)

//util
void die(char *msg);
bool lossy_filter(int lossy);//true=forward, false=drop

//TAP interface
int tap_open(char* ifname);//open a TAP interface, return fd
int tap_read(int fd, char* buffer);//read an Ethernet frame, put to buffer and return the length; return 0 if no frame arrived
bool tap_write(int fd, char* buffer, int len);//write an Ethernet frame

//UDP tunnel
int udp_open(struct sockaddr_in* localSA, struct sockaddr_in* remoteSA);//open a UDP tunnel, return fd
int udp_read(int fd, char* buffer, struct sockaddr_in* remoteSA);//read an Ethernet frame, put to buffer and return the length; return 0 if no frame arrived
bool udp_write(int fd, char* buffer, int len, struct sockaddr_in* remoteSA);//write an Ethernet frame

//console
//(buffer is VNS message, not bare packet; buffer size is MSGSIZE)
int con_init();//initialize the console (stdin/stdout)
bool con_read(char* buffer);//read a message, put to buffer and return true; return false if no message arrived
bool con_write(char* buffer);//write a message, return true on success, return false when stdout is congested and message is dropped

uint32_t vns_getType(char* buffer);//get type
void vns_wAuthReq(char* buffer);//put auth request
void vns_wAuthStatus(char* buffer);//put auth status
void vns_wPacketHdr(char* buffer, int pktlen, char* ifname);//put packet header

//virtual interface
#define vnlif_maxcount 16
struct vnlif {
  char ifname[IFNAMSIZ];//virtual interface name
  struct ether_addr vmac;//virtual MAC
  struct in_addr vip;//virtual IP
  struct in_addr vmask;//virtual IP subnet mask
  char tapname[IFNAMSIZ];//tap interface name
  struct sockaddr_in tip;//tunnel local address
  struct sockaddr_in rtip;//tunnel remote/multicast address
  int tap_fd;
  int udp_fd;
  int lossy;
};
bool vnlif_parse(struct vnlif* result, char* input);
void iflist_hwinfo(c_hwinfo* result, struct vnlif* iflist, int ifcount);
int iflist_find(struct vnlif* iflist, int ifcount, char* ifname);
