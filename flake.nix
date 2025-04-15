{
  description = "An IRI library";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils, ... }@inputs: flake-utils.lib.eachDefaultSystem (system: let
    pkgs = import nixpkgs { inherit system; };
    lib = pkgs.lib;

    php = pkgs.php84.override { 
      curl = myCurl;

      packageOverrides = final: prev: {
        extensions = prev.extensions // {
          curl = prev.extensions.curl.overrideAttrs {
            buildInputs = [ myCurl ];
            configureFlags = [ "--with-curl=${myCurl.dev}" ];
          };
        };
      };
    };
    myCurl = pkgs.curlFull.override {
      http3Support = true;
    };
  in {
    devShell = pkgs.mkShell {
      buildInputs = with pkgs; [
        php
				php.packages.composer

        myCurl
      ];
    };

    shellHook = ''
mkdir -p .vscode
echo "{\"php.validate.executablePath\":\"${php}/bin/php\"}" > .vscode/settings.json
    '';
  });
}