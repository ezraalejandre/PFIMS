import 'package:flutter/material.dart';
import '../widgets/app_header.dart';
import '../services/api_service.dart';
import '../services/user_session.dart';

class SecuritySettingsScreen extends StatefulWidget {
  const SecuritySettingsScreen({super.key});

  @override
  State<SecuritySettingsScreen> createState() =>
      _SecuritySettingsScreenState();
}


class _SecuritySettingsScreenState
    extends State<SecuritySettingsScreen> {


  String _maskedPassword = "••••••••••";
  bool _twoFactorEnabled = true;
  String _twoFactorMethod = "Email";


  final List<String> _sessions = [
    "Windows PC · Chrome\nCurrent session",
    "Android Device · Mobile App\nLast active 2 hours ago",
  ];



  Future<void> _editPassword() async {
    final currentPasswordController = TextEditingController();
    final newPasswordController = TextEditingController();

    bool isSaving = false;
    bool obscureCurrent = true;
    bool obscureNew = true;
    String? currentErrorText;
    String? newErrorText;

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          Future<void> submit() async {
            final currentPassword = currentPasswordController.text;
            final newPassword = newPasswordController.text;

            setDialogState(() {
              currentErrorText = null;
              newErrorText = null;
            });

            var hasError = false;
            if (currentPassword.isEmpty) {
              setDialogState(
                  () => currentErrorText = "Enter your current password");
              hasError = true;
            }
            if (newPassword.trim().isEmpty) {
              setDialogState(() => newErrorText = "Enter a new password");
              hasError = true;
            } else if (newPassword.length < 8) {
              setDialogState(
                  () => newErrorText = "Must be at least 8 characters");
              hasError = true;
            }
            if (hasError) return;

            setDialogState(() => isSaving = true);

            try {
              await ApiService.changePassword(
                UserSession.email,
                currentPassword,
                newPassword,
              );

              if (!mounted) return;
              Navigator.of(dialogContext).pop();

              setState(() {
                _maskedPassword = "••••••••••";
              });

              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text("Password updated")),
              );
            } catch (e) {
              setDialogState(() {
                isSaving = false;
                currentErrorText = e.toString().replaceFirst('Exception: ', '');
              });
            }
          }

          return AlertDialog(
            insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
            title: const Text("Change Password"),
            content: SizedBox(
              width: double.maxFinite,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextField(
                    controller: currentPasswordController,
                    obscureText: obscureCurrent,
                    autofocus: true,
                    enabled: !isSaving,
                    decoration: InputDecoration(
                      labelText: "Current Password",
                      errorText: currentErrorText,
                      suffixIcon: IconButton(
                        icon: Icon(
                          obscureCurrent
                              ? Icons.visibility_off
                              : Icons.visibility,
                        ),
                        onPressed: () => setDialogState(
                            () => obscureCurrent = !obscureCurrent),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: newPasswordController,
                    obscureText: obscureNew,
                    enabled: !isSaving,
                    decoration: InputDecoration(
                      labelText: "New Password",
                      errorText: newErrorText,
                      suffixIcon: IconButton(
                        icon: Icon(
                          obscureNew
                              ? Icons.visibility_off
                              : Icons.visibility,
                        ),
                        onPressed: () =>
                            setDialogState(() => obscureNew = !obscureNew),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: isSaving
                    ? null
                    : () => Navigator.of(dialogContext).pop(),
                child: const Text("Cancel"),
              ),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: kBrandOrange),
                onPressed: isSaving ? null : submit,
                child: isSaving
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Text("Update"),
              ),
            ],
          );
        },
      ),
    );
  }



  void _editTwoFactor(){

    showDialog(
      context: context,

      builder: (_) {


        String selected = _twoFactorMethod;


        return StatefulBuilder(
          builder:(context,setDialogState)=>AlertDialog(

            title:
            const Text(
              "Two Factor Authentication",
            ),


            content:Column(

              mainAxisSize:MainAxisSize.min,

              children:[


                SwitchListTile(
                  title:
                  const Text("Enable 2FA"),

                  value:
                  _twoFactorEnabled,

                  onChanged:(value){

                    setDialogState((){

                      _twoFactorEnabled=value;

                    });

                  },
                ),



                RadioListTile(
                  title:
                  const Text("Email"),

                  value:"Email",

                  groupValue:selected,

                  onChanged:(value){

                    setDialogState((){

                      selected=value!;

                    });

                  },
                ),



                RadioListTile(
                  title:
                  const Text("SMS"),

                  value:"SMS",

                  groupValue:selected,

                  onChanged:(value){

                    setDialogState((){

                      selected=value!;

                    });

                  },
                ),


              ],
            ),



            actions:[

              FilledButton(

                style:
                FilledButton.styleFrom(
                  backgroundColor:kBrandOrange,
                ),

                onPressed:(){

                  setState((){

                    _twoFactorEnabled =
                        _twoFactorEnabled;

                    _twoFactorMethod =
                        selected;

                  });

                  Navigator.pop(context);

                },

                child:
                const Text("Save"),
              )

            ],

          ),
        );
      },
    );

  }



  @override
  Widget build(BuildContext context) {


    return Scaffold(

      backgroundColor:
          const Color(0xFFF5F5F5),

      appBar:
          const _SecurityAppBar(),


      body:ListView(

        padding:
        const EdgeInsets.fromLTRB(
          16,24,16,24
        ),


        children:[



          _Card(

            child:

            Column(

              crossAxisAlignment:
              CrossAxisAlignment.start,


              children:[


                const Text(
                  "PASSWORD",
                  style:
                  TextStyle(
                    fontWeight:
                    FontWeight.w700,
                    color:
                    Colors.black45,
                    fontSize:11,
                  ),
                ),


                const SizedBox(height:14),


                _SecurityRow(

                  icon:
                  Icons.lock_outline,

                  title:
                  "Password",

                  subtitle:
                  _maskedPassword,

                  onTap:
                  _editPassword,
                )

              ],
            ),
          ),




          const SizedBox(height:16),




          _Card(

            child:

            Column(

              crossAxisAlignment:
              CrossAxisAlignment.start,


              children:[


                const Text(
                  "TWO FACTOR AUTHENTICATION",
                  style:
                  TextStyle(
                    fontSize:11,
                    fontWeight:
                    FontWeight.w700,
                    color:
                    Colors.black45,
                  ),
                ),



                const SizedBox(height:14),



                _SecurityRow(

                  icon:
                  Icons.shield_outlined,

                  title:
                  "2FA",

                  subtitle:
                  _twoFactorEnabled
                  ? "Enabled · $_twoFactorMethod"
                  : "Disabled",

                  onTap:
                  _editTwoFactor,

                )

              ],
            ),
          ),





          const SizedBox(height:16),





          _Card(

            child:

            Column(

              crossAxisAlignment:
              CrossAxisAlignment.start,


              children:[


                const Text(
                  "LOGIN SESSIONS",
                  style:
                  TextStyle(
                    fontSize:11,
                    fontWeight:
                    FontWeight.w700,
                    color:
                    Colors.black45,
                  ),
                ),


                const SizedBox(height:10),



                ..._sessions.map(
                  (s)=>Padding(
                    padding:
                    const EdgeInsets.only(
                      bottom:12,
                    ),

                    child:Row(

                      children:[

                        const Icon(
                          Icons.devices,
                          color:Colors.grey,
                        ),

                        const SizedBox(width:12),


                        Expanded(
                          child:Text(
                            s,
                            style:
                            const TextStyle(
                              fontSize:14,
                              fontWeight:
                              FontWeight.w600,
                            ),
                          ),
                        )

                      ],
                    ),
                  ),
                )


              ],
            ),
          )


        ],
      ),

    );

  }

}


// ---- App bar: back arrow + title, with a subtitle line underneath ----
class _SecurityAppBar extends StatelessWidget implements PreferredSizeWidget {
  const _SecurityAppBar();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 6, 16, 10),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.black87),
                  onPressed: () => Navigator.of(context).maybePop(),
                  splashRadius: 22,
                ),
                const Text(
                  'PRIVACY & SECURITY',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Colors.black87),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(left: 48),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'password & account protection',
                  style: TextStyle(fontSize: 12.5, color: Colors.grey.shade600),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(78);
}


class _Card extends StatelessWidget {

 final Widget child;


 const _Card({
  required this.child,
 });


 @override
 Widget build(BuildContext context){

 return Container(

   padding:
   const EdgeInsets.all(16),


   decoration:
   BoxDecoration(

    color:Colors.white,

    borderRadius:
    BorderRadius.circular(14),


    boxShadow:
    const [

      BoxShadow(
        color:
        Color(0x0F000000),

        blurRadius:10,

        offset:
        Offset(0,4),
      )

    ],
   ),


   child:child,

 );

}

}





class _SecurityRow extends StatelessWidget {


 final IconData icon;
 final String title;
 final String subtitle;
 final VoidCallback onTap;


 const _SecurityRow({

 required this.icon,
 required this.title,
 required this.subtitle,
 required this.onTap,

 });


 @override
 Widget build(BuildContext context){

 return InkWell(

  borderRadius:
  BorderRadius.circular(12),

  onTap:onTap,


  child:Row(

   children:[


    Container(

      width:36,
      height:36,

      decoration:
      BoxDecoration(

        color:
        kBrandOrange.withOpacity(.14),

        shape:
        BoxShape.circle,

      ),

      child:
      Icon(
        icon,
        color:kBrandOrange,
        size:18,
      ),

    ),


    const SizedBox(width:12),



    Expanded(

      child:Column(

        crossAxisAlignment:
        CrossAxisAlignment.start,

        children:[


          Text(
            title,
            style:
            const TextStyle(
              fontWeight:
              FontWeight.w700,
            ),
          ),


          Text(
            subtitle,
            style:
            const TextStyle(
              color:Colors.grey,
              fontSize:12,
            ),
          )

        ],
      ),
    ),



    const Icon(
      Icons.edit_outlined,
      size:18,
      color:Colors.grey,
    )

   ],

  ),

 );

}

}