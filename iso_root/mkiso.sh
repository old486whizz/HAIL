#! /bin/sh

#================================================================
#  mkiso.sh does the following:
#     * Accepts IP/server and (optional) netmask
#     * Accepts comment (author+email for example)"
#     * Creates an ISO based on that input
#   it does NOT:
#     * do magic stuff
#   Assumptions:
#     * You know this information
#================================================================
# Version: 1.0       02/04/2012       Paul "1" Sanders
#              Basic stuff. I rule,
#              Initial Version Details
# Don't forget to update "VERSION" variable below
# ---
#              FOR INTERNAL USE!!
# 
#================================================================

#================================================================
# Syntax:
#
#   ./mkso.sh -m IP -s IP [-n NETMASK] [-g GATEWAY] [-d DNS] "comment"
#
#  options:         .
#    d              DNS server to use during install
#    g              Gateway server to use during install
#    m              Temporary IP of server being built
#    n              Netmask (defaults to 255.255.255.0)
#    s              IP of build server
#    comment        Author + email is a good idea
#
#  eg.
#    ./mkiso.sh -m 1.2.3.4 -s 1.2.3.9 -n 255.255.240.0 "iso builder supreme (iso@awesome.net)"
#
#================================================================


#================================================================
# DEFINITION AREA
#    (basic/global variables + functions)
#================================================================
if [[ "${SHELL}" = "/bin/ksh" ]]; then
  set -A argsarr -- $@
elif [[ "${SHELL}" = "/bin/bash" ]]; then
  shopt -s xpg_echo
  typeset -a argsarr=("$@")
fi

DATESTAMP="`date +%Y%m%d_%H%M%S`"
VERSION="1.0"
SCRIPTHOME="./"
LOGDIR="${SCRIPTHOME}/logs"
DNSIP=""
GATEWAYIP=""
MYIP=""
MYNETMASK=""
SERVERIP=""

usage () {
  echo "V.${VERSION}"
  echo "usage: ./mkso.sh -m IP -s IP [-n NETMASK] [-g GATEWAY] [-d DNS] \"comment\"\n"
  echo "options:"
  echo "  -d            | DNS server to use during install"
  echo "  -g            | Gateway server to use during install"
  echo "  -m            | Temporary IP of server being built"
  echo "  -n            | Netmask (defaults to 255.255.255.0)"
  echo "  -s            | IP of build server"
  echo "  comment       | Author + email is a good idea"
  echo ""
  echo " output's: home.iso into the current directory."
  exit 12
}


#================================================================
# Simple validation of parameters:
#================================================================

INDEX=1

while [[ ${OPTIND} -le $# ]]; do
getopts ":m:n:s:d:g:" FLAG
  # first : silences unknown flag errors (handled by '?' instead)
  # : after letter indicates parameter value passed after letter
  case ${FLAG} in
    d) DNSIP=${OPTARG}
      ;;
    g) GATEWAYIP=${OPTARG}
      ;;
    m) MYIP=${OPTARG}
      ;;
    n) MYNETMASK=${OPTARG}
      ;;
    s) SERVERIP=${OPTARG}
      ;;
    ?) [[ -z ${OPTARG} ]] && value[INDEX]=${argsarr[(OPTIND-1)]} && ((INDEX+=1))
       [[ -n ${OPTARG} ]] && echo "** option: '${OPTARG}' not recognized - ignoring. **" && continue
       ((OPTIND+=1))
      ;;
    :) echo "** option: ${OPTARG} needs an argument. **"
       usage
      ;;
  esac
done

((INDEX-=1))

#================================================================
# Main Script:
#================================================================
# use value[#] to access the parameters passed to the script

[[ -z "${MYNETMASK}" ]] && MYNETMASK="255.255.255.0"
for i in "${MYIP}" "${MYNETMASK}" "${SERVERIP}"; do
  [[ -z "${i}" ]] && usage
done

echo "ISO is being built with the following settings:"
echo "==============================================="
printf "== %-10s %30s ==\n" "HOST IP:" "${MYIP}"
printf "== %-10s %30s ==\n" "NETMASK:" "${MYNETMASK}"
printf "== %-10s %30s ==\n" "SERVER IP:" "${SERVERIP}"
printf "== %-10s %30s ==\n" "GATEWAY IP:" "${GATEWAYIP}"
printf "== %-10s %30s ==\n" "DNS IP:" "${DNSIP}"
printf "== %-10s %30s\n" "COMMENT:" "'${value[1]}'"
echo "==============================================="

if [[ -z "${DNSIP}" ]]; then
  DNSREPL=""
else
  DNSREPL="dns=${DNSIP}"
fi

if [[ -z "${GATEWAYIP}" ]]; then
  GWREPL=""
else
  GWREPL="gateway=${GATEWAYIP}"
fi

sed "s/_MY_IP_/${MYIP}/; s/_MY_NETMASK_/${MYNETMASK}/; s/_SERVER_IP_/${SERVERIP}/; s/gateway=_MY_GATEWAY_/${GWREPL}/g; s/dns=_MY_DNS_/${DNSREPL}/g" isolinux/isolinux.cfg.base >isolinux/isolinux.cfg

mkisofs -m mkiso.sh -m home.iso -o ./home.iso -b isolinux/isolinux.bin -c isolinux/boot.cat -no-emul-boot -boot-load-size 4 -boot-info-table -R -T -A "Fedora Custom Install CD" -p "${value[1]}" ./
[[ "$?" != "0" ]] && echo "** ERROR IN CREATING ISO FILE **"

rm isolinux/isolinux.cfg

