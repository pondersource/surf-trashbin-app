# SURF Trashbin App
## A project for SURF
Deleted shared files, appear in the trashbin of the group owners in OwnCloud.

Each group *g* has a functional account named f_*g* which is the admin for that group. No real user is supposed to log in with the functional account. Instead the functional account shares a folder with a special user called the owner. The purpose for this app is to expose the trashbin of the functional account to the owner user so that there is one extra trashbin icon per group that the current user is an owner of. The onwer user can then see the trash files and restore or permanently remove them.

## Setup for testing and demo
Note that this guide is tested only in the Gitpod.io environment.
