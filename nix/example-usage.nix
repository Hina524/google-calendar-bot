# Example: How to use calendar-bot in your dotfiles
#
# 1. Add to flake.nix inputs:
#
#    inputs = {
#      calendar-bot = {
#        url = "github:Hina524/google-calendar-bot";
#        inputs.nixpkgs.follows = "nixpkgs";
#      };
#    };
#
# 2. Pass to NixOS configuration:
#
#    nixosConfigurations.yourhost = nixpkgs.lib.nixosSystem {
#      modules = [
#        inputs.calendar-bot.nixosModules.default
#        ./configuration.nix
#      ];
#    };
#
# 3. Enable in configuration.nix:

{ config, pkgs, ... }:

{
  # SOPS secrets configuration
  sops.secrets."calendar-bot/env" = {
    sopsFile = ../secrets/calendar-bot.yaml;
  };

  # Enable the calendar bot service
  services.calendar-bot = {
    enable = true;
    port = 8080;
    environmentFile = config.sops.secrets."calendar-bot/env".path;
  };

  # Cloudflare Tunnel configuration
  # Add to your existing tunnel ingress:
  # {
  #   hostname = "calendar-bot.yourdomain.com";
  #   service = "http://localhost:8080";
  # }
}
