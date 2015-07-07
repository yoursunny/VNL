#include "vnlsvc.h"
#include <sys/ioctl.h>
#include <fcntl.h>
#include <linux/if_tun.h>

int tun_alloc(char* dev, int flags) {
  struct ifreq ifr;
  int fd, err;
  char* clonedev = "/dev/net/tun";
  if ((fd = open(clonedev, O_RDWR)) < 0 ) return fd;

  memset(&ifr, 0, sizeof(ifr));
  ifr.ifr_flags = flags;
  if (*dev) strncpy(ifr.ifr_name, dev, IFNAMSIZ);

  if ((err = ioctl(fd, TUNSETIFF, &ifr)) < 0) {
    close(fd);
    return err;
  }
  strncpy(dev, ifr.ifr_name, IFNAMSIZ);
  return fd;
}

int tap_open(char* ifname) {
  int fd = tun_alloc(ifname, IFF_TAP | IFF_NO_PI);
  if (fd < 0) die("tap_open tun_alloc");
  if (ioctl(fd, TUNSETNOCSUM, 1) < 0) die("tap_open TUNSETNOCSUM");
  return fd;
}

int tap_read(int fd, char* buffer) {
  int nread = read(fd, buffer, FRAMESIZE);
  if (nread < 0) die("tap_read");
  return nread;
}

bool tap_write(int fd, char* buffer, int len) {
  if (len == 0) return false;
  int nwrite = write(fd, buffer, len);
  if (nwrite < 0) die("tap_write");
  return (nwrite == len);
}
