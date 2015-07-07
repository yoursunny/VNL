#include "vnlsvc.h"
#include <fcntl.h>
#include <errno.h>

int con_init() {
  int flags;
  flags = fcntl(0, F_GETFL, 0); flags |= O_NONBLOCK; fcntl(0, F_SETFL, flags);
  flags = fcntl(1, F_GETFL, 0); flags |= O_NONBLOCK; fcntl(1, F_SETFL, flags);
}

char con_readbuf[MSGSIZE];
uint32_t con_nread = 0;

bool con_read2(int expect_len) {//read until expected length, return true if got this far
  int nread;
  while (con_nread < expect_len) {
    nread = read(0, con_readbuf + con_nread, expect_len - con_nread);
    if (nread == -1) {
      if (errno == EAGAIN || errno == EWOULDBLOCK) return false;
      else die("con_read2 read");
    } else {
      con_nread += nread;
    }
  }
  return true;
}

bool con_read(char* buffer) {
  if (!con_read2(4)) return false;
  int commandlen = ntohl(*((uint32_t*)con_readbuf));
  if (!con_read2(commandlen)) return false;
  memcpy(buffer, con_readbuf, commandlen);
  con_nread = 0;
  return true;
}

char con_writebuf[MSGSIZE];
int con_nwrite = -1;

bool con_write2(char* buffer) {//write from buffer, return true if completed
  if (con_nwrite == -1) return true;
  if (buffer == NULL) buffer = con_writebuf;
  int commandlen = ntohl(*((uint32_t*)buffer));
  if (con_nwrite == commandlen) return true;
  int nwrite;
  while (con_nwrite < commandlen) {
    nwrite = write(1, buffer + con_nwrite, commandlen - con_nwrite);
    if (nwrite == -1) {
      if (errno == EAGAIN || errno == EWOULDBLOCK) return false;
      else die("con_write2 write");
    } else {
      con_nwrite += nwrite;
    }
  }
  return true;
}

bool con_write(char* buffer) {
  if (!con_write2(NULL)) {
    perror("con_write block");
    return false;
  }
  con_nwrite = 0;
  if (con_write2(buffer)) {//optimization: don't copy if can write at once
    con_nwrite = -1;
  } else {
    memcpy(con_writebuf, buffer, MSGSIZE);
  }
  return true;
}

uint32_t vns_getType(char* buffer) {
  c_base* basehdr = (c_base*)buffer;
  return ntohl(basehdr->mType);
}

void vns_wAuthReq(char* buffer) {
  c_auth_request* authreq = (c_auth_request*)buffer;
  authreq->mLen = htonl(sizeof(c_auth_request));
  authreq->mType = htonl(VNS_AUTH_REQUEST);
}

void vns_wAuthStatus(char* buffer) {
  c_auth_status* authstatus = (c_auth_status*)buffer;
  authstatus->mLen = htonl(sizeof(c_auth_status));
  authstatus->mType = htonl(VNS_AUTH_STATUS);
  authstatus->auth_ok = true;
}

void vns_wPacketHdr(char* buffer, int pktlen, char* ifname) {
  c_packet_header* pkthdr = (c_packet_header*)buffer;
  pkthdr->mLen = htonl(sizeof(c_packet_header) + pktlen);
  pkthdr->mType = htonl(VNSPACKET);
  strncpy(pkthdr->mInterfaceName, ifname, 16);
}
