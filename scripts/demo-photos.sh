#!/usr/bin/env bash
# Downloads the demo listing photos into storage/demo-photos/ (run from the app folder, on the server).
# All are free to use commercially under the Unsplash License (https://unsplash.com/license); credit appreciated.
set -e
mkdir -p storage/demo-photos
get () { curl -fsSL "https://unsplash.com/photos/$2/download?force=true&w=1600" -o "storage/demo-photos/$1.jpg" && echo "ok   $1" || echo "FAIL $1 (a placeholder will be used)"; }
get villa     zRBujTnBwpc   # Timo Wielink — modern villa, Camps Bay
get apartment kTKTSuUW4zM   # Amy Harrison — apartment building, Cape Town
get estate    gAVyP4WwYZY   # Mpho Mojapelo — Hidden Valley Wine Estate, Stellenbosch
get farm      S5VMHmmxPzk   # Tanya Paquet — Postberg, West Coast
get aerial    yqqNtRP0Wxw   # Samuel F — aerial farmland
get mall      fxtbAjHR4gU   # Ilanit Ohana — shopping mall interior
get mall2     cGNjdVLDAik   # Paul Lichtblau — mall corridor
# No mining photo: the coal listing uses a branded placeholder unless you add storage/demo-photos/mine.jpg yourself.
