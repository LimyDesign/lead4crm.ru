#!/usr/bin/env bash

function log {
  echo "EXECUTING: $@" | tee -a $LOG
  $@ |& tee -a $LOG
  return ${PIPESTATUS[0]}
}

function lec {
  log $@
  ec=$?
  echo -e "EXIT CODE: $ec\n" | tee -a $LOG

  if (($ec > 0)); then
    echo "ERROR ON LAST COMMAND EXECUTION. EXIT." | tee -a $LOG
    exit $ec;
  fi
}

function h1 {
  echo -e "==== $1 ====\n" | tee -a $LOG
}

function h2 {
  echo -e "\n[o] $1\n" | tee -a $LOG
}