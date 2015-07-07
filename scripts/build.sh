#!/bin/bash

cd guest-apps

cd vnlsvc
make
cd ..

cd udpsum
gcc -o udpsum --std=c99 udpsum.c
cd ..
