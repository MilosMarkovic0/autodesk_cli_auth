# autodesk-cli-auth-tool
OAuth 3-legged automation for access to the Forge platform via the CLI

autodesk-cli-auth-tool.php is used to 3-legged auth and re-auth against Forge generating tokens for access to A360/Fusion Team/BIM360 Team files.

There are two usage paradigms:
    1. Initial authentication opening the browser and allowing the user to authorize their account and get a token with an expiration time.
    1. Refresh authentication allowing the token to be refreshed before the expiration time.

**Note: If authentication is not refreshed before the token expires, then initial authentication is required 

## Usage: 

`php autodesk-cli-auth-tool.php [OPTIONS]
    -m --mode=[initial|refresh]
    -t --tokenfile=<file path to store the current token and expiry>
    -k --keyfile=<file path to the location of a file with your Forge client id and secret>`
    
## Examples:
    * _Initial:_ `php autodesk-cli-auth-tool.php --mode=initial --tokenfile=\"temp.txt\" --keyfile=\"keyfile.txt\"`

    * _Refresh:_ `php autodesk-cli-auth-tool.php --mode=refresh --tokenfile=\"temp.txt\" --keyfile=\"keyfile.txt\"`

## Key File:
    
    The Key File format should be a plain-text file with the name of the variable followed by its value in the file separated by an equals sign (e.g. NAME=value):
    
    FORGE_CLIENT_ID
    
    FORGE_CLIENT_SECRET
    
    FORGE_CALLBACK_URL