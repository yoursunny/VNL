all : vnlsvc

vnlsvc : *.h *.c
	gcc -std=gnu99 -o vnlsvc util.c tap.c udp.c console.c vnlif.c vnlsvc.c

clean:
	rm -f *.o *~ vnlsvc
