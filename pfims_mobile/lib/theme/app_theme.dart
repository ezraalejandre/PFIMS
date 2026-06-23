
import 'package:flutter/material.dart';

class AppColors {
  static const Color dark = Color(0xff111827);
  static const Color orange = Color(0xffF59E0B);
  static const Color light = Color(0xffF4F6FA);
  static const Color card = Colors.white;
}

class AppTheme {
  static ThemeData get theme {
    return ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: AppColors.light,
      colorScheme: ColorScheme.fromSeed(
        seedColor: AppColors.orange,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.dark,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      cardTheme: CardThemeData(
        color: AppColors.card,
        elevation: 2,
        margin: const EdgeInsets.all(8),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(18)),
        ),
      ),
    );
  }
}
