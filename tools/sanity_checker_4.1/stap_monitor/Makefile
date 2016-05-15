obj-m += stap_monitor.o

KV = $(shell uname -r)
KDIR := /lib/modules/$(KV)/build
PWD := $(shell pwd)

.PHONY: clean

default:
	$(MAKE) -C $(KDIR) SUBDIRS=$(PWD) modules

clean:
	$(MAKE) -C $(KDIR) SUBDIRS=$(PWD) clean
	rm -f modules.order
