{
  description = "Google Calendar to Discord notification bot";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
  };

  outputs = { self, nixpkgs }:
  {
    nixosModules.default = import ./nix/module.nix { inherit self; };
  };
}
