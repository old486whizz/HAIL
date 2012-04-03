#----------------------------------------------------------------------
# ** DEFAULT ** Kickstart file for builds of
# Fedora 14+ x86 i686 (32 bit)
#
# For information on the kickstart installation mechanism,
# see the online Fedora customization guide.
#
# Version 2.0a      Paul 1 Sanders
#                  28/08/2011
#   All-new version using centralized YUM server for kickstart
#   Much nicer/cleaner
#   2 versions for updating + new builds
#----------------------------------------------------------------------


#----------------------------------------------------------------------
# Defaults / standard settings:
# =============
lang en_GB
keyboard "uk"
skipx
text
rootpw CHANGE_ME_IMMEDIATELY
auth --useshadow
firewall --disabled
timezone  Europe/London
firstboot --disable
selinux _SELINUX_OPTION_
reboot
upgrade
url  --url=http://_YUMREPO_IP_/repo/_YUMREPO_OPTION_
repo --name="fedora.hme"  --baseurl=http://_YUMREPO_IP_/repo/_YUMREPO_OPTION_
repo --name="updates.hme" --baseurl=http://_YUMREPO_IP_/repo/_YUMREPO_OPTION_.updates
repo --name="custom"      --baseurl=http://_YUMREPO_IP_/repo/custom

repo --name="adobe.inst"  --baseurl=http://linuxdownload.adobe.com/linux/i386/
repo --name="vbox.inst"   --baseurl=http://download.virtualbox.org/virtualbox/rpm/fedora/_YUMREPO_OPTION_/i686
repo --name="livna.inst"  --baseurl=http://rpm.livna.org/repo/_YUMREPO_OPTION_/i386
repo --name="rpmff.inst"  --baseurl=http://download1.rpmfusion.org/free/fedora/releases/_YUMREPO_OPTION_/Everything/i386/os/
repo --name="rpmffu.inst" --baseurl=http://download1.rpmfusion.org/free/fedora/updates/_YUMREPO_OPTION_/i386/
repo --name="rpmfn.inst"  --baseurl=http://download1.rpmfusion.org/nonfree/fedora/releases/_YUMREPO_OPTION_/Everything/i386/os/
repo --name="rpmfnu.inst" --baseurl=http://download1.rpmfusion.org/nonfree/fedora/updates/_YUMREPO_OPTION_/i386/

#----------------------------------------------------------------------


#----------------------------------------------------------------------
# Disk Configuration:
# =============
# I don't think this needs one.... We'll see


#----------------------------------------------------------------------


#----------------------------------------------------------------------
# Network Configuration:
# =============
network --hostname=_HOSTNAME_OPTION_ --gateway=_GATEWAY_OPTION_

_NETWORK_LOOP_
# output like: network --bootproto=static --onboot=yes --noipv6 --device=<ETH> --ip=<IP_ADDY> --netmask=<NETMASK> --ethtool=<ETHTOOL>
#----------------------------------------------------------------------


#----------------------------------------------------------------------
# Packages (external file):
# =============
%packages --ignoredeps --nobase
_PACKAGE_LIST_
#----------------------------------------------------------------------


#----------------------------------------------------------------------
# POST install script (before reboot)
# =============
%post

# ==================================
#   Config file:
_SERVICES_LOOP_
# =================================

# =================================
# Remove reserved space from these filesystems:
# /, /home
tune2fs -m 0 -r 0 /dev/mapper/rootvg._HOSTNAME_OPTION_-rootlv
tune2fs -m 0 -r 0 /dev/mapper/rootvg._HOSTNAME_OPTION_-homelv
# =================================

# =================================
# sort out syncing of important files:

# disable normal repos:
yum-config-manager --disable fedora
yum-config-manager --disable updates

if [[ ! -f /root/.ssh/id_rsa.pub ]]; then
                 # generate temp public key if required
  ssh-keygen -t rsa -N "" -C "temp@_HOSTNAME_OPTION_"

                 # send to http://_IP_/HAIL/sync.php
  curl -F "key=@/root/.ssh/id_rsa.pub" -F "host=_HOSTNAME_OPTION_" http://_YUMREPO_IP_/HAIL/sync_req.php

                 # get ssh host key from server
  ssh -o StrictHostKeyChecking=no initial_sync@_YUMREPO_IP_ "echo '_HOSTNAME_OPTION_' >/dev/null"

                 # sync root's .ssh dir (remote dir wins)
  unison -times -prefer older -batch -ui text ssh://initial_sync@_YUMREPO_IP_//server/sync/home/root/.ssh /root/.ssh

fi

                 # tell the server first sync done
curl -F "host=_HOSTNAME_OPTION_" http://_YUMREPO_IP_/HAIL/sync_req.php

                 # sleep to allow server to replace authorized_keys
sleep 5

                 # sync rest using unison as root
unison -batch -ui text ssh://_YUMREPO_IP_//server/sync/home/root /root >>/unison.err

                 # Add crontask to sync using unison
                 # job in /root/sync_job.sh
echo "0,10,20,30,40,50 * * * * /root/sync_job.sh" |crontab

# cd /etc/yum.repos.d/
# wget http://download.virtualbox.org/virtualbox/rpm/fedora/virtualbox.repo

# =================================

%end
