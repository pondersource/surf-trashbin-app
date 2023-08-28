# SURF Trashbin App
## A project for SURF
Deleted shared files, appear in the trashbin of the group owners in OwnCloud.

Each group *g* has a functional account named f_*g* which is the admin for that group. No real user is supposed to log in with the functional account. Instead the functional account shares a folder with a special user called the owner. The purpose for this app is to expose the trashbin of the functional account to the owner user so that there is one extra trashbin icon per group that the current user is an owner of. The onwer user can then see the trash files and restore or permanently remove them.

## Setup for testing and demo
Note that this guide is tested only in the Gitpod.io environment
1. Clone the [PonderSource dev-stock repository](https://github.com/pondersource/dev-stock) or open it as a new Gitpod project
2. Call `./scripts/init-surf-trashbin.sh` in the terminal
3. Call `./scripts/testing-surf-trashbin.sh` in the terminal. Note that this script is derived from the testing setup code provided by SURF [here](https://github.com/SURFnet/rd-oc-shared-trashbin/blob/master/owncloud/init.sh).
4. Open the firefox container instance in your browser under the port 5800. Inside the browser:
5. Navigate to `https://oc1.docker/`
6. Login using credentials `jennifer` as both the username and the password. Trashbin icons with titles `biochemistry` and `bioinformatics` should be visible in the left navigation bar above the main trashbin icon.
7. Navigate to the `biochemistry` trashbin and verify that it is indeed empty
8. Go back to `All files` and open the `f_biochemsitry_shared` folder and create a new file there. The `upload` option after clicking + is the easiest way to do so.
9. Delete the file you just created
10. Now open the `biochemistry` trashbin again and verify that the file you just deleted shows up in the list
11. You can try restoring or permanently deleting the file from the trashbin

**Notice** that the deleted file also independently appears in the main trashbin of the current user. That is because the current user is also the one who created the file. What shows up under the `biochemistry`trashbin is actually the trashbin of the functional account that originally shared the folder. So both the owner user and the user who created the file are able to restore it and the repear appearance in the trashbin folders is not a bug but the intended behavior.
