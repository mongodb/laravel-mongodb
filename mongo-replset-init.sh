#!/bin/bash
mongodb1=`getent hosts ${MONGO1} | awk '{ print $1 }'`

port=${PORT:-27018}

echo "Waiting for startup.."
until mongo --host ${mongodb1}:${port} --eval 'quit(db.runCommand({ ping: 1 }).ok ? 0 : 2)' &>/dev/null; do
  printf '.'
  sleep 1
done

echo "Started.."

echo setup.sh time now: `date +"%T" `
mongo --host ${mongodb1}:${port} <<EOF
   var cfg = {
        "_id": "${RS}",
        "protocolVersion": 1,
        "members": [
            {
                "_id": 0,
                "host": "${MONGO1}:${port}",
                "priority": 2
            },
            {
                "_id": 1,
                "host": "${MONGO2}:${port}",
                "priority": 0
            },
            {
                "_id": 2,
                "host": "${MONGO3}:${port}",
                "priority": 0
            }
        ]
    };
    rs.initiate(cfg, { force: true });
    rs.reconfig(cfg, { force: true });
EOF
