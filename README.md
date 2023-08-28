# SURF Trashbin App
## A project for SURF
Deleted shared files, appear in the trashbin of the group owners in OwnCloud.

Each group *g* has a functional account named f_*g* which is the admin for that group. No real user is supposed to log in with the functional account. Instead the functional account shares a folder with a special user called the owner. The purpose for this app is to expose the trashbin of the functional account to the owner user so that there is one extra trashbin icon per group that the current user is an owner of. The onwer user can then see the trash files and restore or permanently remove them.

## Setup for testing and demo
Note that this guide is tested only in the Gitpod.io environment
1. Clone the [PonderSource dev-stock repository](https://github.com/pondersource/dev-stock) or open it as a new Gitpod project
2. Call `./scripts/init-surf-trashbin.sh` in the terminal
3. Call `./scripts/testing-surf-trashbin.sh` in the terminal. Note that this script is derived from the testing setup code provided by SURF [here](https://github.com/SURFnet/rd-oc-shared-trashbin/blob/master/owncloud/init.sh).
4. 
